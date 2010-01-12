<?php

/**
 * DataObject to keep a cache of information about a given SVN url.
 * Designed to be used in conjunction with SvnUpdateTask, run once an hour, to keep relatively
 * up-to-date information about a SVN repository without excessively hammering a server.
 */
class SvnInfoCache extends DataObject {
	static $db = array(
		"URL" => "Varchar(255)",
		"LatestRevPacked" => "Int",
		"ChildDirsPacked" => "Text",
		"NeedsLatestRev" => "Boolean",
		"NeedsChildDirs" => "Boolean",
	);
	
	/**
	 * Get an info cache object for the given URL
	 */
	static function for_url($url) {
		$obj = DataObject::get_one('SvnInfoCache', "URL = '" . Convert::raw2sql($url) . "'");
		if(!$obj) {
			$obj = new SvnInfoCache();
			$obj->URL = $url;
			$obj->write();
		}
		return $obj;
	}
	
	static function build_up_tree_source($url){
		preg_match('/^(.+)\/([^\/]+)$/', $url, $matches);
		if($can = self::is_not_leaf_node($url)){
			$svnInfo = SvnInfoCache::for_url($url)->childDirs();
			$retVal = array();
			foreach($svnInfo as $k=>$v){
				$val = self::build_up_tree_source($url."/".$k);
				$retVal[$k] = $val;
			}
			return $retVal;
		}else{//is a leaf
			return $matches[2];
		}
	}
	
	static function is_not_leaf_node($url){
		$CLI_url = escapeshellarg($url);
		$retVal = 0;
		$output = array();

		exec("unset DYLD_LIBRARY_PATH && svn ls --xml $CLI_url", $output, $retVal);
		if($retVal == 0) {
			$subdirs = new SimpleXMLElement(implode("\n", $output));
			foreach($subdirs->xpath('//entry') as $entry) {
				$kind = $entry->attributes()->asXML();
				if(strpos($kind, "kind=\"dir\"") === false){
					return false;
				}
			}
			return true;
		}
	}
	
	/**
	 * Return the latest revision number for the given URL.
	 */
	function getLatestRev() {
		$rev = $this->LatestRevPacked;
		if(!$rev && $this->URL) {
			$this->NeedsLatestRev = 1;
			$this->update();
			$rev = $this->LatestRevPacked;
		}
		return $rev;
	}

	/**
	 * Return an array of the child directories
	 */
	function childDirs() {
		$dirs = $this->getField('ChildDirsPacked');
		if(!$dirs && $this->URL) {
			$this->NeedsChildDirs = 1;
			$this->update();
			$dirs = $this->getField('ChildDirsPacked');
		}
		
		return unserialize($dirs);
	}

	
	/**
	 * Update this info-cache's data from the underlying subversion repository.
	 */
	function update() {
		$CLI_url = escapeshellarg($this->URL);

		// Update LatestRev
		if($this->NeedsLatestRev) {
			$retVal = 0;
			$output = array();
			exec("unset DYLD_LIBRARY_PATH && svn info --xml $CLI_url &> /dev/stdout", $output, $retVal);
			if($retVal == 0 && preg_match("/\<\?xml/", $output[0])) {
				try {
					$info = new SimpleXMLElement(implode("\n", $output));
					if($info->entry->commit['revision']) {
						$this->LatestRevPacked = (string)$info->entry->commit['revision'];
					}
				} catch(Exception $e) {
				}
			}
		}

		// Update ChildDirs
		if($this->NeedsChildDirs) {
			$retVal = 0;
			$output = array();

			exec("unset DYLD_LIBRARY_PATH && svn ls --xml $CLI_url", $output, $retVal);
			if($retVal == 0) {
				$subdirs = new SimpleXMLElement(implode("\n", $output));
				foreach($subdirs->xpath('//entry') as $entry) {
					$name = (string)$entry->name;
					$date = (string)$entry->commit->date;
					$rev = (string)$entry->commit['revision'];
					
					$subdirInfo[$name] = array('date' => $date, 'rev' => $rev);
				}
			}
			$this->ChildDirsPacked = serialize($subdirInfo);
		}

		$this->write();
		
	}
}
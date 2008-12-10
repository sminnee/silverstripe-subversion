<?php

/**
 * This class turns a subversion URL into a downloadable .tar.gz file,
 * caching it intelligently to prevent needless regeneration whilst still
 * ensuring that the file returned is always up-to-date
 * 
 * Usage:
 *
 * Within a Controller or RequestHandler:
 * <code>
 * function TrunkDownload() {
 *     return CachedSvnArchiver($this, "Trunk", "http://svn.silverstripe.com/open/modules/phpinstaller/trunk", "assets/downloads");
 * }
 * </code>
 *
 * Within your template:
 * <code>
 * <a href="$TrunkDownload.URL">$TrunkDownload.Title</a>
 * </code>
 */
class CachedSvnArchiver extends RequestHandler {
	protected $parent, $name;
	
	protected $cacheDir, $cacheURL, $url;
	protected $_cache = array();
	
	static $allowed_actions = array(
		'generate',
	);
	
	/**
	 * @param $url The subversion URL to enable for download
	 */
	function __construct($parent, $name, $url, $cacheDir = 'assets/downloads') {
		$this->parent = $parent;
		$this->name = $name;
		
		if($cacheDir[0] == '/') user_error("Please supply a relative directory for the cacheDir - it needs to be in the web root", E_USER_ERROR);
		$this->cacheDir = BASE_PATH . '/' . $cacheDir;
		$this->cacheURL = BASE_URL . '/' . $cacheDir;
		
		$this->url = $url;
	}
	
	function Link() {
		return Controller::join_links($this->parent->Link(), $this->name);
	}
	
	function URL() {
		if(file_exists($this->fullFilename())) return $this->fullURL();
		else return Controller::join_links($this->Link(), 'generate');
	}

	function Filename() {
		if(!isset($this->_cache['Filename'])) {
			$parts = $this->svnParts();
			if($parts['type'] == 'tags') $this->_cache['Filename'] = "{$parts['module']}-v{$parts['instance']}.tar.gz";
			else $this->_cache['Filename'] = "{$parts['module']}-{$parts['instance']}-r" . $this->currentRev() . ".tar.gz";
		}
		return $this->_cache['Filename'];
	}

	function fullFilename() {
		return $this->cacheDir . '/' . $this->Filename();
	}
	function fullURL() {
		return $this->cacheURL . '/' . $this->Filename();
	}

	/**
	 * Returns the latest revision # of the SVN url
	 */
	function currentRev() {
		$CLI_url = escapeshellarg($this->url);
		
		$retVal = 0;
		$output = array();
		exec("svn info --xml $CLI_url &> /dev/stdout", $output, $retVal);
		
		if($retVal == 0) {
			$info = new SimpleXMLElement(implode("\n", $output));
			if($info->entry->commit['revision']) return $info->entry->commit['revision'];
		}
		
		// If we didn't return by now, then we have an error
		user_error("Error calling svn (return val $retVal): " . implode("\n", $output), E_USER_ERROR);
	}
	
	/**
	 * Returns an array of info from the subversion URL.
	 * blog/trunk will go to array('module' => 'blog', 'type' => 'trunk', 'instance' => 'trunk')
	 * blog/tags/0.2.2 will go to array('module' => 'blog', 'type' => 'tags', 'instance' => '0.2.2')
	 * blog/branches/0.2.2 will go to array('module' => 'blog', 'type' => 'branches', 'instance' => '0.2.2')
	 * blog/tags/rc/0.2.2-rc1 will go to array('module' => 'blog', 'type' => 'tags', 'instance' => '0.2.2-rc1')
	 */
	function svnParts() {
		$parts = array('module' => null, 'type' => null, 'instance' => null);
		if(preg_match('/\/([^\/]+)\/(branches|tags|trunk|sandbox)/', $this->url, $matches)) {
			$parts['module'] = $matches[1];
			$parts['type'] = $matches[2];
		}
		$parts['instance'] = basename($this->url);
		return $parts;
	}
	
	/**
	 * Actually create the .tar.gz file 
	 */
	function generate() {
		// Give ourselves a reasonable amount of time
		if(ini_get('max_execution_time') < 300) set_time_limit(300);
		
		$folder = str_replace('.tar.gz','', $this->Filename());

		// If the file has been generated since we clicked the link, then just redirect there
		if(file_exists($this->fullFilename())) {
			Director::redirect($this->fullURL());
			return;

		// If someone else has started producing the file, then wait for them to finish.
		// Wait for 120 seconds and if it's still not ready, then build it ourselves
		} else if(file_exists(TEMP_FOLDER .'/'. $folder)) {
			for($i=0;$i<120;$i++) {
				sleep(1);
				if(file_exists($this->fullFilename())) {
					Director::redirect($this->fullURL());
					return;
				}
			}
		}
		
		
		// Otherwise, let's do the build.
		$CLI_folder = escapeshellarg($folder);
		$CLI_tmp = escapeshellarg(TEMP_FOLDER);
		$CLI_outputFile = escapeshellarg($this->fullFilename());
		$CLI_url = escapeshellarg($this->url);
		
		$destDir = dirname($this->fullFilename());
		if(!is_dir($destDir) && !mkdir(dirname($destDir), 0777, true)) {
			user_error("Couldn't create directory: " . $destDir, E_USER_ERROR);
		}

		$retVal = 0;
		$output = array();
		exec("cd $CLI_tmp && svn export $CLI_url $CLI_folder && tar czf $CLI_outputFile $CLI_folder && rm -r $CLI_folder", $output, $retVal);
			
		if($retVal == 0) {
			Director::redirect($this->fullURL());
		} else {
			user_error("Couldn't produce .tar.gz of output (return val $retVal): " . implode("\n", $output));
		}
	}
}

?>
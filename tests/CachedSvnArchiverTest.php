<?php
class CachedSvnArchiverTest extends SapphireTest {
	
	/**
	 * Tests {@link CachedSvnArchiver->svnParts()}
	 */
	function testSvnParts() {
		$cases = array(
			'http://svn.silverstripe.com/open/modules/blog/trunk' => array(
				'module' => 'blog',
				'type' => 'trunk'
			),
			'http://svn.silverstripe.com/open/modules/forum/tags/0.1' => array(
				'module' => 'forum',
				'type' => 'tags'
			),
			'svn://svn.somewhere.com/modules/calendar' => array(
				'module' => 'svn.somewhere.com',
				'type' => 'trunk'
			)
		);
		
		foreach($cases as $url => $info) {
			$archiver = new CachedSvnArchiver(null, 'Download', $url);
			$svnParts = $archiver->svnParts();
			
			$this->assertEquals(basename($url), $svnParts['instance']);
			$this->assertEquals($info['module'], $svnParts['module']);
			$this->assertEquals($info['type'], $svnParts['type']);
			
			unset($archiver);
			unset($svnParts);
		}
	}
	
}
?>
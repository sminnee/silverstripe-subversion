<?php

class SvnUpdateTask extends DailyTask {
	function process() {
		foreach(DataObject::get("SvnInfoCache") as $cache) {
			$cache->update();
			$cache->destroy();
		}
	}
}

class SvnUpdateTask_Manual extends BuildTask {
	
	function run($request) {
		echo "Running Update \n";
		
		$update = new SvnUpdateTask();
		
		$update->process();
	}
}
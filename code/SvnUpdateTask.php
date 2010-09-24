<?php

class SvnUpdateTask extends DailyTask {
	function process() {
		foreach(DataObject::get("SvnInfoCache") as $cache) {
			$cache->update();
			$cache->destroy();
		}
	}
}
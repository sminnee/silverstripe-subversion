<?php

class SvnUpdateTask extends HourlyTask {
	function process() {
		foreach(DataObject::get("SvnInfoCache") as $cache) {
			$cache->update();
			$cache->destroy();
		}
	}
}
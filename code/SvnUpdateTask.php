<?php

class SvnUpdateTask extends HourlyTask {
	funcion process() {
		foreach(DataObject::get("SvnInfoCache") as $cache) {
			$cache->update();
			$cache->destroy();
		}
	}
}
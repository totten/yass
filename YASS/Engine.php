<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/ConflictResolver.php';

class YASS_Engine {
	static function bidir(
		YASS_DataStore $srcData, YASS_SyncStore $srcSync,
		YASS_DataStore $destData, YASS_SyncStore $destSync,
		YASS_ConflictResolver $conflictResolver
	) {
		require_once 'YASS/Algorithm/Bidir.php';
		$job = new YASS_Algorithm_Bidir();
		$job->run($srcData, $srcSync, $destData, $destSync, $conflictResolver);
		return $job;
	}
}

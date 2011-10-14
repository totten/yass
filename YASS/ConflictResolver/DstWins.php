<?php

require_once 'YASS/Engine.php';
require_once 'YASS/ConflictResolver.php';

class YASS_ConflictResolver_DstWins extends YASS_ConflictResolver {
	function resolve(YASS_Algorithm $job, YASS_SyncState $srcSyncState, YASS_SyncState $destSyncState) {
		YASS_Engine::singleton()->transfer($job->dest, $job->src, array($destSyncState));
	}
}

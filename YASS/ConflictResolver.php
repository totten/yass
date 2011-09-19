<?php

require_once 'YASS/Algorithm.php';
require_once 'YASS/SyncState.php';

abstract class YASS_ConflictResolver {
	abstract function resolve(YASS_Algorithm $job, YASS_SyncState $srcSyncState, YASS_SyncState $destSyncState);
}


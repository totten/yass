<?php

require_once 'YASS/ConflictResolver.php';

class YASS_ConflictResolver_SrcWins extends YASS_ConflictResolver {
	function resolve(YASS_Algorithm $job, YASS_SyncState $srcSyncState, YASS_SyncState $destSyncState) {
		$entity = $job->srcData->getEntity($srcSyncState->entityType, $srcSyncState->entityGuid);
		$job->destData->putEntity($entity);
		$job->destSync->setSyncState($srcSyncState->entityType, $srcSyncState->entityGuid, $srcSyncState->modified);
	}
}

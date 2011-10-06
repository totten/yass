<?php

require_once 'YASS/ConflictResolver.php';

class YASS_ConflictResolver_DstWins extends YASS_ConflictResolver {
	function resolve(YASS_Algorithm $job, YASS_SyncState $srcSyncState, YASS_SyncState $destSyncState) {
		$entity = $job->destData->getEntity($destSyncState->entityType, $destSyncState->entityGuid);
		$job->srcData->putEntity($entity);
		$job->srcSync->setSyncState($destSyncState->entityType, $destSyncState->entityGuid, $destSyncState->modified);
	}
}

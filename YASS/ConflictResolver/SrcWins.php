<?php

require_once 'YASS/ConflictResolver.php';

class YASS_ConflictResolver_SrcWins extends YASS_ConflictResolver {
	function resolve(YASS_Algorithm $job, YASS_SyncState $srcSyncState, YASS_SyncState $destSyncState) {
		$entity = $job->src->data->getEntity($srcSyncState->entityType, $srcSyncState->entityGuid);
		$job->dest->data->putEntity($entity);
		$job->dest->sync->setSyncState($srcSyncState->entityType, $srcSyncState->entityGuid, $srcSyncState->modified);
	}
}

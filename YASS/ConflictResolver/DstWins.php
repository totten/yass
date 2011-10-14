<?php

require_once 'YASS/ConflictResolver.php';

class YASS_ConflictResolver_DstWins extends YASS_ConflictResolver {
	function resolve(YASS_Algorithm $job, YASS_SyncState $srcSyncState, YASS_SyncState $destSyncState) {
		$entity = $job->dest->data->getEntity($destSyncState->entityType, $destSyncState->entityGuid);
		$job->src->data->putEntity($entity);
		$job->src->sync->setSyncState($destSyncState->entityType, $destSyncState->entityGuid, $destSyncState->modified);
	}
}

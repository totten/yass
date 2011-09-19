<?php

require_once 'YASS/ConflictResolver.php';

class YASS_ConflictResolver_Exception extends YASS_ConflictResolver {
	function resolve(YASS_Algorithm $job, YASS_SyncState $srcSyncState, YASS_SyncState $destSyncState) {
		throw new Exception(sprintf('Conflict detected for %s:%s (%s:%s vs %s:%s)',
		  $srcSyncState->entityType, $srcSyncState->entityGuid,
		  $srcSyncState->modified->replicaId, $srcSyncState->modified->tick,
		  $destSyncState->modified->replicaId, $destSyncState->modified->tick
		));
	}
}

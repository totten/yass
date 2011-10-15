<?php

require_once 'YASS/ConflictResolver.php';

class YASS_ConflictResolver_Exception extends YASS_ConflictResolver {
	function resolve(YASS_Algorithm $job, YASS_SyncState $srcSyncState, YASS_SyncState $destSyncState) {
		$srcModifiedReplica = YASS_Engine::singleton()->getReplicaById($srcSyncState->modified->replicaId);
		$srcModifiedName = ($srcModifiedReplica ? $srcModifiedReplica->name : $srcSyncState->modified->replicaId);
		$destModifiedReplica = YASS_Engine::singleton()->getReplicaById($destSyncState->modified->replicaId);
		$destModifiedName = ($destModifiedReplica ? $destModifiedReplica->name : $destSyncState->modified->replicaId);
		throw new Exception(sprintf('Conflict detected for %s (%s:%s vs %s:%s)',
		  $srcSyncState->entityGuid,
		  $srcModifiedName, $srcSyncState->modified->tick,
		  $destModifiedName, $destSyncState->modified->tick
		));
	}
}

<?php

require_once 'YASS/ConflictResolver.php';

class YASS_ConflictResolver_Exception extends YASS_ConflictResolver {
    function resolve(YASS_Conflict $conflict) {
        // Note: The replicas being synchronized may not necessarily be the replicas which produced the changes
        $leftModifiedReplica = YASS_Engine::singleton()->getReplicaById($conflict->leftSyncState->modified->replicaId);
        $leftModifiedName = ($leftModifiedReplica ? $leftModifiedReplica->name : $conflict->leftSyncState->modified->replicaId);
        $rightModifiedReplica = YASS_Engine::singleton()->getReplicaById($conflict->rightSyncState->modified->replicaId);
        $rightModifiedName = ($rightModifiedReplica ? $rightModifiedReplica->name : $conflict->rightSyncState->modified->replicaId);
        throw new Exception(sprintf('Conflict detected for %s: (%s:%s via %s) vs (%s:%s via %s))',
            $conflict->entityGuid,
            $leftModifiedName, $conflict->leftSyncState->modified->tick, $conflict->leftReplica->name,
            $rightModifiedName, $conflict->rightSyncState->modified->tick, $conflict->rightReplica->name
        ));
    }
}

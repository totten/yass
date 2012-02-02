<?php

require_once 'YASS/ConflictResolver.php';

class YASS_ConflictResolver_Exception extends YASS_ConflictResolver {
    function resolve(YASS_Conflict $conflict) {
        // Note: The replicas being synchronized may not necessarily be the replicas which produced the changes
        $leftModifiedReplica = YASS_Engine::singleton()->getReplicaById($conflict->left->syncState->modified->replicaId);
        $leftModifiedName = ($leftModifiedReplica ? $leftModifiedReplica->name : $conflict->left->syncState->modified->replicaId);
        $rightModifiedReplica = YASS_Engine::singleton()->getReplicaById($conflict->right->syncState->modified->replicaId);
        $rightModifiedName = ($rightModifiedReplica ? $rightModifiedReplica->name : $conflict->right->syncState->modified->replicaId);
        throw new Exception(sprintf('Conflict detected for %s: (%s:%s via %s) vs (%s:%s via %s))',
            $conflict->entityGuid,
            $leftModifiedName, $conflict->left->syncState->modified->tick, $conflict->left->replica->name,
            $rightModifiedName, $conflict->right->syncState->modified->tick, $conflict->right->replica->name
        ));
    }
}

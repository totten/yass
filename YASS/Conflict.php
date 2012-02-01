<?php

require_once 'YASS/Engine.php';
require_once 'YASS/Entity.php';
require_once 'YASS/SyncState.php';

class YASS_Conflict {

    /**
     * Create a batch of several conflicts
     *
     * @var $guids array(entityGuid)
     * @var $leftSyncStates array(entityGuid => YASS_SyncState) syncstates for all $guids; may include extra, unrelated syncstates
     * @var $rightSyncStates array(entityGuid => YASS_SyncState) syncstates for all $guids; may include extra, unrelated syncstates
     * @return array(entityGuid => YASS_Conflict)
     */
    public static function createBatch(YASS_Replica $leftReplica, YASS_Replica $rightReplica, $guids, $leftSyncStates, $rightSyncStates) {
        $leftEntities = $leftReplica->data->getEntities($guids);
        $rightEntities = $rightReplica->data->getEntities($guids);
        $conflicts = array();
        foreach ($guids as $guid) {
            $conflicts[$guid] = new YASS_Conflict($leftReplica, $rightReplica, $leftSyncStates[$guid], $rightSyncStates[$guid], $leftEntities[$guid], $rightEntities[$guid]);
        }
        return $conflicts;
    }
    
    /**
     * @var string, type of entity in conflict; could be null if both $leftEntity and $rightEntity are non-existent
     */
    var $entityType;
    
    /**
     * @var string
     */
    var $entityGuid;

    /**
     * @var YASS_Replica
     */
    var $leftReplica;
    
    /**
     * @var YASS_Replica
     */
    var $rightReplica;
    
    /**
     * @var YASS_SyncState
     */
    var $leftSyncState;
    
    /**
     * @var YASS_SyncState
     */
    var $rightSyncState;
    
    /**
     * @var YASS_Entity
     */
    var $leftEntity;
    
    /**
     * @var YASS_Entity
     */
    var $rightEntity;

    function __construct(YASS_Replica $leftReplica, YASS_Replica $rightReplica, YASS_SyncState $leftSyncState, YASS_SyncState $rightSyncState, YASS_Entity $leftEntity = NULL, YASS_Entity $rightEntity = NULL) {
        $this->entityType = $leftEntity->exists ? $leftEntity->entityType : $rightEntity->entityType;
        $this->entityGuid = $leftSyncState->entityGuid;
        $this->leftReplica = $leftReplica;
        $this->rightReplica = $rightReplica;
        $this->leftSyncState = $leftSyncState;
        $this->rightSyncState = $rightSyncState;
        $this->leftEntity = $leftEntity;
        $this->rightEntity = $rightEntity;
    }
    
    /**
     * Treat left variant as unconditional winner
     */
    function pickLeft() {
        return $this->pickWinner($this->leftReplica, $this->rightReplica, $this->leftSyncState, $this->leftEntity, $this->rightSyncState, $this->rightEntity);
    }
    
    /**
     * Treat right variant as unconditional winner
     */
    function pickRight() {
        return $this->pickWinner($this->rightReplica, $this->leftReplica, $this->rightSyncState, $this->rightEntity, $this->leftSyncState, $this->leftEntity);
    }
    
    /**
     * Pick one variant as an unconditional loser -- and the other as unconditional loser
     */
    function pickWinner(YASS_Replica $winnerReplica, YASS_Replica $loserReplica, YASS_SyncState $winnerSyncState, YASS_Entity $winnerEntity, YASS_SyncState $loserSyncState, YASS_Entity $loserEntity) {
        /*
        $conflict = array(
            // 'entity_type' => $this->entityType,
            'entity_id' => $winnerSyncState->entityGuid,
            'win_replica_id' => $winnerSyncState->modified->replicaId,
            'win_tick' => $winnerSyncState->modified->tick,
            'win_entity' => (array)$winnerEntity,
            'lose_replica_id' => $loserSyncState->modified->replicaId,
            'lose_tick' => $loserSyncState->modified->tick,
            'lose_entity' => (array)$loserEntity,
            'timestamp' => arms_util_time(),
        );
        // drupal_write_record('yass_conflict', $conflict);
        */
        YASS_Engine::singleton()->transfer($winnerReplica, $loserReplica, array($winnerSyncState));
    }
    
    /**
     * Replace the two conflicted variants with one combined variant
     */
    function pickReplacement(YASS_SyncState $newSyncState, YASS_Entity $newEntity) {
        throw new RuntimeException("YASS_Conflict::pickReplacement() Not implemented");
    }
    
}

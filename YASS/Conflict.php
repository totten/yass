<?php

/*
 +--------------------------------------------------------------------+
 | YASS                                                               |
 +--------------------------------------------------------------------+
 | Copyright ARMS Software LLC (c) 2011-2012                          |
 +--------------------------------------------------------------------+
 | This file is a part of YASS.                                       |
 |                                                                    |
 | YASS is free software; you can copy, modify, and distribute it     |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007.                                       |
 |                                                                    |
 | YASS is distributed in the hope that it will be useful, but        |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | Additional permissions may be granted. See LICENSE.txt for         |
 | details.                                                           |
 +--------------------------------------------------------------------+
*/

require_once 'YASS/Engine.php';
require_once 'YASS/Entity.php';
require_once 'YASS/SyncState.php';

/**
 * Represent a conflict in which two replicas have competing revisions to the same entity
 *
 * When a conflict-resolver receives an instance of YASS_Conflict, it should examine the entities and call a pickWinner function
 *
 * FIXME: The pickWinner functions send their decisions to the sync/datastores immediately. Instead, we should record the decisions and perform a subsequent batch update.
 */
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
     * @var _YASS_Conflict_Part the replicas/entities identified as conflicted
     */
    var $left, $right;
    
    /**
     * @var _YASS_Conflict_Part the replicas/entities which are chosen to win/lose the conflict
     */
    var $winner, $loser;
    
    function __construct(YASS_Replica $leftReplica = NULL, YASS_Replica $rightReplica = NULL, YASS_SyncState $leftSyncState = NULL, YASS_SyncState $rightSyncState = NULL, YASS_Entity $leftEntity = NULL, YASS_Entity $rightEntity = NULL) {
        $this->entityType = $leftEntity->exists ? $leftEntity->entityType : $rightEntity->entityType;
        $this->entityGuid = $leftEntity->entityGuid;
        $this->left = new _YASS_Conflict_Part($leftReplica, $leftSyncState, $leftEntity);
        $this->right = new _YASS_Conflict_Part($rightReplica, $rightSyncState, $rightEntity);
        $this->winner = NULL;
        $this->loser = NULL;
    }
    
    /**
     * Treat left variant as unconditional winner
     */
    function pickLeft() {
        return $this->pickWinner($this->left, $this->right);
    }
    
    /**
     * Treat right variant as unconditional winner
     */
    function pickRight() {
        return $this->pickWinner($this->right, $this->left);
    }
    
    /**
     * Pick one variant as an unconditional loser -- and the other as unconditional loser
     */
    function pickWinner(_YASS_Conflict_Part $winner, _YASS_Conflict_Part $loser) {
        $this->winner = $winner;
        $this->loser = $loser;
        if ($winner->replica) $winner->replica->conflictListeners->onPickWinner($this);
        if ($loser->replica) $loser->replica->conflictListeners->onPickWinner($this);
        
        // drupal_write_record('yass_conflict', $conflict);
        // YASS_Engine::singleton()->transfer($winner->replica, $loser->replica, array($winner->syncState));
    }
}

class _YASS_Conflict_Part {
    
    /**
     * @var YASS_Replica
     */
    var $replica;
    
    /**
     * @var YASS_SyncState
     */
    var $syncState;
    
    /**
     * @var YASS_Entity
     */
    var $entity;
    
    function __construct(YASS_Replica $replica = NULL, YASS_SyncState $syncState = NULL, YASS_Entity $entity = NULL) {
        $this->replica = $replica;
        $this->syncState = $syncState;
        $this->entity = $entity;
    }
}

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

require_once 'YASS/IConflictListener.php';

/**
 * Record any conflicts by creating a new entity
 */
class YASS_ConflictListener_LogEntity implements YASS_IConflictListener {
    /**
     * @param $spec array with keys:
     *  - hackConflictLog: callback, function($logData, $conflict)=>$logData
     */
    function __construct($spec) {
        require_once 'YASS/Context.php';
        require_once 'YASS/Entity.php';
        
        $this->hackConflictLog = $spec['hackConflictLog'];
    }
    
    function onPickWinner(YASS_Conflict $conflict) {
        $data = array(
            // 'entity_type' => $this->entityType,
            'entity_id' => $conflict->entityGuid,
            'win_replica_id' => $conflict->winner->syncState->modified->replicaId,
            'win_tick' => $conflict->winner->syncState->modified->tick,
            'win_entity' => (array)$conflict->winner->entity,
            'lose_replica_id' => $conflict->loser->syncState->modified->replicaId,
            'lose_tick' => $conflict->loser->syncState->modified->tick,
            'lose_entity' => (array)$conflict->loser->entity,
            'timestamp' => arms_util_time(),
        );
        if ($this->hackConflictLog) {
            $data = call_user_func($this->hackConflictLog, $data, $conflict);
        }
        $log = new YASS_Entity(
            YASS_Engine::singleton()->createGuid(),
            'yass_conflict',
            $data
        );
        
        $addendum = YASS_Context::get('addendum');
        $addendum->add($log);
    }
}

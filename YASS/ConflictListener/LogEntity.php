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
        $winnerVersion = $conflict->winner->syncState ? $conflict->winner->syncState->modified : new YASS_Version(0,0);
        $loserVersion = $conflict->loser->syncState ? $conflict->loser->syncState->modified : new YASS_Version(0,0);
        $data = array(
            // 'entity_type' => $this->entityType,
            'entity_id' => $conflict->entityGuid,
            'win_replica_id' => $winnerVersion->replicaId,
            'win_tick' => $winnerVersion->tick,
            'win_entity' => (array)$conflict->winner->entity,
            'lose_replica_id' => $loserVersion->replicaId,
            'lose_tick' => $loserVersion->tick,
            'lose_entity' => (array)$conflict->loser->entity,
            'timestamp' => arms_util_time(),
        );
        $data = $this->hackContactRelation($data, $conflict);
        if ($this->hackConflictLog) {
            $data = call_user_func($this->hackConflictLog, $data, $conflict);
        }
        $log = new YASS_Entity(
            YASS_Engine::singleton()->createGuid(),
            'yass_conflict',
            $data
        );
        
        YASS_Context::get('addendum')->add($conflict->winner->replica, $log);
    }
    
    /**
     * Transform the log entity in ways that are specific to our schema. This needs to be moved elsewhere.
     */
    function hackContactRelation($data, YASS_Conflict $conflict) {
        switch ($conflict->winner->entity->entityType) {
            case 'civicrm_contact':
              $data['contact_id'] = $conflict->winner->entity->entityGuid;
              break;
            case 'civicrm_address':
            case 'civicrm_phone':
            case 'civicrm_email':
              $data['contact_id'] = $conflict->winner->entity->data['contact_id'];
              break;
            default:
        }
        return $data;
    }

}

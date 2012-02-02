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

/**
 * An addendum is a collection of entities and syncstates which have been originally created or modified by the sync engine during the sync process.
 *
 * For example, when the sync engine encounters a conflict, it may wish to add a log record (which would be visible
 * to all replicas) indicating how the conflict was resolved. The log record was not part of the initially planned
 * data sync, and it will need its own revision number.
 * 
 * There could be concurrency issues with how YASS_Addendum::apply() uses the markSeen() operation (e.g. interleaved
 * changes made by other threads could be inadvertently flagged as seen). To mitigate this risk, use 'master' as the
 * $logicalReplica. The 'master' is only manipulated by the background worker thread.
 */
class YASS_Addendum {
    /**
     * @var YASS_Replica the replica from which any updates will appear to come
     */
    var $logicalReplica;

    /**
     * @var array(entityGuid => YASS_Entity)
     */
    var $todoEntities;
  
    /**
     * @var array(entityGuid => YASS_Version)
     */
    var $todoVersions;
    
    function __construct(YASS_Replica $logicalReplica) {
        $this->logicalReplica = $logicalReplica;
    }
    
    /**
     * Add a new entity
     *
     * @param $version optional, specificially set the replicaId/tick of the modified entity; only use this for back-dating revisions. If a new revision is required, set this to NULL and one will be created
     */
    function add(YASS_Entity $entity, YASS_Version $version = NULL) {
        $this->todoEntities[$entity->entityGuid] = $entity;
        if ($version) {
            $this->todoVersions[$entity->entityGuid] = $version;
        }
    }
    
    /**
     * Update an existing entity
     *
    function update(YASS_Entity $entity, YASS_SyncState $oldSyncState) {
        $this->todoEntities[$entity->entityGuid] = $entity;
        $this->oldSyncStates[$entity->entityGuid] = $oldSyncState;
    } // */
    
    /**
     * Apply the addenda on all listed replicas
     *
     * @param $replicas arrayYASS_Replica)
     * @return void
     */
    function apply($replicas) {
        if (empty($this->todoEntities)) return;
        if (empty($replicas)) return;
        
        $tick = NULL; // YASS_Version, optional
        $newVersions = array();
        foreach ($this->todoEntities as $entity) {
            if ($this->todoVersions[$entity->entityGuid]) {
                $newVersions[$entity->entityGuid] = $this->todoVersions[$entity->entityGuid];
            } else {
                if (!$tick) {
                    $tick = $this->logicalReplica->sync->tick();
                }
                $newVersions[$entity->entityGuid] = $tick;
            }
        }
        
        $engine = YASS_Engine::singleton();
        foreach ($replicas as $replica) {
            $engine->transferData($this->logicalReplica, $replica, $this->todoEntities, $newVersions);
            if ($tick) {
                $replica->sync->markSeens(array($tick));
            }
        }
    }
}

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
    const MAX_ITERATIONS = 5;

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
    
    /**
     * @var array(replicaId => array(entityGuid))
     */
    var $todoTicks;
    
    /**
     * @var bool whether another round of sync is needed
     */
    var $syncRequired;
    
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
     * Ensure that an entity is marked with a new tick at the end of synchronization.
     *
     * Use this in lieu of add() if you don't have a full and propery copy of the entity available
     *
     * @param $replicaId int, the replica from which to read the entity content 
     */
    function tick($entityGuid, $replicaId = NULL) {
        if ($replicaId === NULL ) {
            $replicaId = $this->logicalReplica->id;
        }
        $this->todoTicks[$replicaId][] = $entityGuid;
    }
    
    function setSyncRequired($syncRequired) {
        $this->syncRequired =  $syncRequired;
    }
    
    function isSyncRequired() {
        return ($this->syncRequired);
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
     * @return void
     */
    function apply() {
        if (!empty($this->todoEntities)) {
            $tick = NULL; // YASS_Version, optional
            $newVersions = array(); // array(entityGuid => YASS_Version)
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
            $engine->transferData($this->logicalReplica, $this->logicalReplica, $this->todoEntities, $newVersions);
            $this->setSyncRequired(TRUE);
        }
        if (!empty($this->todoTicks)) {
            foreach ($this->todoTicks as $replicaId => $entityGuids) {
                $replica = YASS_Engine::singleton()->getReplicaById($replicaId);
                $tick = $replica->sync->tick();
                $newVersions = array(); // array(entityGuid => YASS_Version)
                foreach ($entityGuids as $entityGuid) {
                    $newVersions[$entityGuid] = $tick;
                }
                $replica->sync->setSyncStates($newVersions);
            }
            $this->setSyncRequired(TRUE);
        }
    }
    
    function clear() {
        $this->syncRequired = FALSE;
        $this->todoEntities = array();
        $this->todoVersions = array();
        $this->todoTicks = array();
    }
}

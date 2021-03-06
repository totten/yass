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
 */
class YASS_Addendum {
    const MAX_ITERATIONS = 5;

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
    
    function __construct() {
        $this->clear();
    }
    
    /**
     * Add a new entity
     *
     * @param $version optional, specificially set the replicaId/tick of the modified entity; only use this for back-dating revisions. If a new revision is required, set this to NULL and one will be created
     */
    function add(YASS_Replica $replica, YASS_Entity $entity) {
        $version = $replica->sync->tick();
        // Note: putEntities' filter pipeline believes it can modify anything its given
        YASS_Engine::singleton()->transferData($replica, $replica, 
            array(clone $entity),
            array($entity->entityGuid => $version)
        ); 
        $this->setVersion($replica, $entity->entityGuid, $version);
    }
    
    /**
     * Ensure that an entity is marked with a new tick at the end of synchronization.
     *
     * Use this in lieu of add() if you don't have a full and proper copy of the entity available
     *
     */
    function setVersion(YASS_Replica $replica, $entityGuid, YASS_Version $version) {
        $this->todoVersions[$replica->getEffectiveId()][$entityGuid] = $version;
    }
    
    /**
     * Ensure that an entity is marked with a new tick at the end of synchronization.
     *
     * Use this in lieu of add() if you don't have a full and proper copy of the entity available
     *
     * @param $replicaId int, the replica from which to read the entity content 
     */
    function tick($entityGuid, YASS_Replica $replica) {
        $this->todoTicks[$replica->getEffectiveId()][] = $entityGuid;
    }
    
    /**
     * Return a list of entities which are modified via this addendum
     *
     * @return array(entityGuid)
     */
    function getEntityGuids() {
        $guids = array();
        foreach ($this->todoVersions as $replicaId => $newVersions) {
            $guids = array_merge($guids, array_keys($newVersions));
        }
        foreach ($this->todoTicks as $replicaId => $entityGuids) {
            $guids = array_merge($guids, $entityGuids);
        }
        return $guids;
    }
    
    function setSyncRequired($syncRequired) {
        $this->syncRequired =  $syncRequired;
    }
    
    function isSyncRequired() {
        return ($this->syncRequired);
    }
    
    /**
     * Apply the addenda on all listed replicas
     *
     * @return void
     */
    function apply() {
        if (!empty($this->todoVersions)) {
            foreach ($this->todoVersions as $replicaId => $newVersions) {
                $replica = YASS_Engine::singleton()->getReplicaById($replicaId);
                $replica->sync->setSyncStates($newVersions);
            }
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
        $this->todoTicks = array();
        $this->todoVersions = array();
    }
    
    function isEmpty() {
        return ($this->syncRequired == FALSE) && empty($this->todoTicks) && empty($this->todoVersions);
    }
    
    /**
     * Copy all the pending updates from another addendum, merging them into this addendum.
     */
    function mergeIn(YASS_Addendum $other) {
        $this->syncRequired = $this->syncRequired || $other->syncRequired;
        foreach ($other->todoTicks as $replicaId => $entityGuids) {
            if ($this->todoTicks[$replicaId]) {
                $this->todoTicks[$replicaId] = array_unique(array_merge($this->todoTicks[$replicaId], $entityGuids));
            } else {
                $this->todoTicks[$replicaId] = $entityGuids;
            }
        }
        foreach ($other->todoVersions as $replicaId => $entityVersions) {
            if ($this->todoVersions[$replicaId]) {
                $this->todoVersions[$replicaId] = array_merge($this->todoVersions[$replicaId], $entityVersions);
            } else {
                $this->todoVersions[$replicaId] = $entityVersions;
            }
        }
    }
}

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

require_once 'YASS/DataStore.php';
require_once 'YASS/Replica.php';
require_once 'YASS/SyncStore.php';

class YASS_SyncStore_LocalizedMemory extends YASS_SyncStore {

    var $replica;
    
    /**
     * @var array(replicaId => YASS_Version)
     */
    var $lastSeen;
    
    /**
     * @var array(entityType => array(lid => YASS_SyncState))
     */
    var $syncStates;
    
    /**
     * 
     */
    public function __construct(YASS_Replica $replica) {
        $this->replica = $replica;
        $this->lastSeen = array($this->replica->getEffectiveId() => new YASS_Version($this->replica->getEffectiveId(), 0));
        $this->syncStates = array();
    }

    /**
     * Find a list of revisions that have been previously applied to a replica
     *
     * @return array(replicaId => YASS_Version)
     */
    function getLastSeenVersions() {
        return $this->lastSeen;
    }
    
    /**
     * Assert that the given replica includes the data for (replica,tick)
     */
    protected function markSeen(YASS_Version $lastSeen) {
        if (!$this->lastSeen[$lastSeen->replicaId]
            || $lastSeen->tick > $this->lastSeen[$lastSeen->replicaId]->tick 
        ) {
            $this->lastSeen[ $lastSeen->replicaId ] = $lastSeen;
        }
    }
    
    /**
     * Find all records in a replica which have been modified since the given point
     *
     * @return array(entityGuid => YASS_SyncState)
     */
    protected function getModified(YASS_Version $lastSeen = NULL) {
        $modified = array();
        if (!$lastSeen) {
            foreach ($this->syncStates as $type => $syncStates) {
                $modified = array_merge($modified, array_values($syncStates));
            }
        } else {
            foreach ($this->syncStates as $type => $syncStates) {
                foreach ($syncStates as $lid => $syncState) {
                    if ($syncState->modified->replicaId == $lastSeen->replicaId
                        && $syncState->modified->tick > $lastSeen->tick
                    ) {
                        $modified[] = $syncState;
                    }
                }
            }
        }
        
        $result = arms_util_array_index(array('entityGuid'), $modified);
        return $result;
    }
    
    /**
     * Obtain the next available version number
     *
     * @return YASS_Version
     */
    function tick() {
        // update tick count
        if ($this->lastSeen[$this->replica->getEffectiveId()]) {
            $this->lastSeen[$this->replica->getEffectiveId()] = $this->lastSeen[$this->replica->getEffectiveId()]->next();
        } else {
            $this->lastSeen[$this->replica->getEffectiveId()] = new YASS_Version($this->replica->getEffectiveId(), 1);
        }
        return $this->lastSeen[$this->replica->getEffectiveId()];
    }
    
    /**
     *
     */
    function onUpdateEntity($entityGuid) {
        $this->setSyncState($entityGuid, $this->tick());
    }
    
    /**
     * Determine the sync state of a particular entity
     *
     * @return YASS_SyncState
     */
    protected function getSyncState($entityGuid) {
        list ($type, $lid) = $this->replica->mapper->toLocal($entityGuid);
        if (!($type && $lid)) {
            return FALSE;
        }
        return $this->syncStates[$type][$lid];
    }
    
    /**
     * Set the sync state of an entity
     */
    protected function setSyncState($entityGuid, YASS_Version $modified) {
        list ($type, $lid) = $this->replica->mapper->toLocal($entityGuid);
        if (!($type && $lid)) {
            throw new Exception(sprintf('[%s] Failed to store state for unmapped entity (GUID=%s). DataStore should have mapped GUID to local ID.', $this->replica->name, $entityGuid));
        }
        
        // update tick count
        if ($this->syncStates[$type][$lid]) {
            $this->syncStates[$type][$lid]->modified = $modified;
        } else {
            $this->syncStates[$type][$lid] = new YASS_SyncState($entityGuid, 
                $modified, $modified);
        }
    }
    
    /**
     * Destroy any last-seen or sync-state data
     */
    function destroy() {
        $this->lastSeen = array($this->replica->getEffectiveId() => new YASS_Version($this->replica->getEffectiveId(), 0));
        $this->syncStates = array();
    }
    
    /**
     * Forcibly increment the versions of entities to make the current replica appear newest
     */
    function updateAllVersions() {
        foreach ($this->syncStates as $type => $syncStates) {
            foreach ($syncStates as $lid => $syncState) {
                $this->onUpdateEntity($syncState->entityGuid);
            }
        }
    }
}

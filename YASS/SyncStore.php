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

require_once 'YASS/ReplicaListener.php';
require_once 'YASS/Version.php';
require_once 'YASS/ISyncStore.php';

/**
 * Convenience class which allows one to implement a syncstore using single-record
 * methods (instead of multi-record methods).
 */
abstract class YASS_SyncStore extends YASS_ReplicaListener implements YASS_ISyncStore {
    
    /**
     * Assert that this replica includes the data for (replicaId,tick)
     */
    protected abstract function markSeen(YASS_Version $lastSeen);
    
    /**
     * Assert that this replica includes the data for several (replicaId,tick) pairs
     *
     * @param $lastSeens array(YASS_Version)
     */
    function markSeens($lastSeens) {
        foreach ($lastSeens as $lastSeen) {
            $this->markSeen($lastSeen);
        }
    }
    
    /**
     * Find all records in a replica which have been modified since the given point
     *
     * @return array(entityGuid => YASS_SyncState)
     */
    protected abstract function getModified(YASS_Version $lastSeen = NULL);
    
    /**
     * Find all records in a replica which have been modified since the given point
     *
     * @param $remoteLastSeenVersions array(replicaId => YASS_Version) List version records which have already been seen
     * @return array(entityGuid => YASS_SyncState)
     */
    function getModifieds($remoteLastSeenVersions) {
        $localChanges = array();  // array(entityGuid => YASS_SyncState)
        $localLastSeenVersions = $this->getLastSeenVersions();
        foreach ($localLastSeenVersions as $replicaId => $localVersion) {
            $remoteVersion = $remoteLastSeenVersions[$replicaId] ? $remoteLastSeenVersions[$replicaId] : new YASS_Version($replicaId, 0);
            // print_r(array('localChanges += ', $this->getModified($remoteVersion)));
            $localChanges += $this->getModified($remoteVersion);
        }
        return $localChanges;
    }
    
    /**
     * Determine the sync state of a particular entity
     *
     * @return YASS_SyncState
     */
    protected abstract function getSyncState($entityGuid);

    /**
     * Determine the sync state of a particular entity
     *
     * @param $entityGuids array(entityGuid)
     * @return array(entityGuid => YASS_SyncState)
     */
    function getSyncStates($entityGuids) {
        $syncStates = array();
        foreach ($entityGuids as $entityGuid) {
            $syncStates[$entityGuid] = $this->getSyncState($entityGuid);
        }
        return $syncStates;
    }
    
    /**
     * Set the sync state of an entity
     */
    protected abstract function setSyncState($entityGuid, YASS_Version $modified);
    
    /**
     * Set the sync states of several entities
     *
     * @param $states array(entityGuid => YASS_Version)
     */
    function setSyncStates($states) {
        foreach ($states as $entityGuid => $modified) {
            $this->setSyncState($entityGuid, $modified);
        }
    }
    
    /**
     * Obtain the next available version number
     *
     * @return YASS_Version
     */
    abstract function tick();
}

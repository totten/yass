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

require_once 'YASS/Version.php';

/**
 * @public
 */
interface YASS_ISyncStore {
    /**
     * Find a list of revisions that have been previously applied to a replica
     *
     * @return array(replicaId => YASS_Version)
     */
    function getLastSeenVersions();
    
    /**
     * Assert that this replica includes the data for several (replicaId,tick) pairs
     *
     * @param $lastSeens array(YASS_Version)
     */
    function markSeens($lastSeens);
    
    /**
     * Find all records in a replica which have been modified since the given point
     *
     * @param $remoteLastSeenVersions array(replicaId => YASS_Version) List version records which have already been seen
     * @return array(entityGuid => YASS_SyncState)
     */
    function getModifieds($remoteLastSeenVersions);
    
    /**
     * Determine the sync state of a particular entity
     *
     * @param $entityGuids array(entityGuid)
     * @return array(entityGuid => YASS_SyncState)
     */
    function getSyncStates($entityGuids);
    
    /**
     * Set the sync states of several entities
     *
     * @param $states array(entityGuid => YASS_Version)
     */
    function setSyncStates($states);
    
    /**
     * Forcibly increment the versions of entities to make the current replica appear newest
     */
    function updateAllVersions();
    
    /**
     * Destroy any last-seen or sync-state data
     */
    function destroy();
}

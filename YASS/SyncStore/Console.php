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

/**
 * A fake syncstore which prints incoming syncstates on the console
 */
class YASS_SyncStore_Console extends YASS_SyncStore {

    var $replica;
    
    /**
     * @var array(guid => YASS_SyncState)
     */
    var $syncStates;
    
    /**
     * 
     */
    public function __construct(YASS_Replica $replica) {
        $this->replica = $replica;
        $this->syncStates = array();
        $this->prefix = 'sync/';
    }

    /**
     * Find a list of revisions that have been previously applied to a replica
     *
     * @return array(replicaId => YASS_Version)
     */
    function getLastSeenVersions() {
        throw new Exception('Not implemented: YASS_SyncStore_Console::getModified()');
    }
    
    /**
     * Assert that the given replica includes the data for (replica,tick)
     */
    protected function markSeen(YASS_Version $lastSeen) {
    }
    
    /**
     * Find all records in a replica which have been modified since the given point
     *
     * @return array(entityGuid => YASS_SyncState)
     */
    protected function getModified(YASS_Version $lastSeen = NULL) {
        throw new Exception('Not implemented: YASS_SyncStore_Console::getModified()');
    }
    
    /**
     * Obtain the next available version number
     *
     * @return YASS_Version
     */
    function tick() {
    }
    
    /**
     * Determine the sync state of a particular entity
     *
     * @return YASS_SyncState
     */
    protected function getSyncState($entityGuid) {
        throw new Exception('Not implemented: YASS_SyncStore_Console::getSyncState()');
    }
    
    /**
     * Set the sync state of an entity
     */
    protected function setSyncState($entityGuid, YASS_Version $modified) {
        // FIXME: setSyncState should really accept a syncstate as input; this is a 
        $this->printSyncState(new YASS_SyncState($entityGuid,  $modified, $modified));
    }
    
    function printSyncState(YASS_SyncState $entity) {
        $arr = (array)$entity;
        $arr = arms_util_implode_tree('/', $arr, TRUE);
        
        $guid = $arr['entityGuid'];
        unset($arr['entityGuid']);
        
        foreach ($arr as $key => $value) {
            //printf("%s%-60s \"%s\"\n", $this->prefix, $guid . '/' . $key, $value);
            printf("%s%s \"%s\"\n", $this->prefix, $guid . '/' . $key, $value);
        }
    }
    
    /**
     * Destroy any last-seen or sync-state data
     */
    function destroy() {
    }
    
    /**
     * Forcibly increment the versions of entities to make the current replica appear newest
     */
    function updateAllVersions() {
        printf("%supdateAllVersions()\n", $this->prefix);
    }
}

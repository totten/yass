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

require_once 'YASS/Proxy.php';
require_once 'YASS/Version.php';

/**
 * A syncstore which uses arms_interlink RPC calls to synchronize against a remote syncstore
 */
class YASS_SyncStore_Proxy extends YASS_Proxy implements YASS_ISyncStore {

    /**
     * @var YASS_Replica
     */
    var $replica;
    
    /**
     * 
     */
    public function __construct(YASS_Replica $replica) {
        module_load_include('service.inc', 'yass');
        $this->replica = $replica;
        parent::__construct($replica->spec['remoteSite'], $replica->spec['remoteReplica'], $replica);
    }
    /**
     * Find a list of revisions that have been previously applied to a replica
     *
     * @return array(replicaId => YASS_Version)
     */
    function getLastSeenVersions() {
        $result = $this->_proxy('yass.getLastSeenVersions');
        YASS_Proxy::decodeAllInplace('YASS_Version', $result);
        return $result;
    }
    
    /**
     * Assert that this replica includes the data for several (replicaId,tick) pairs
     *
     * @param $lastSeens array(YASS_Version)
     */
    function markSeens($lastSeens) {
        YASS_Proxy::encodeAllInplace('YASS_Version', $lastSeens);
        $result = $this->_proxy('yass.markSeens', $lastSeens);
    }
    
    /**
     * Find all records in a replica which have been modified since the given point
     *
     * @param $remoteLastSeenVersions array(replicaId => YASS_Version) List version records which have already been seen
     * @return array(entityGuid => YASS_SyncState)
     */
    function getModifieds($remoteLastSeenVersions) {
        YASS_Proxy::encodeAllInplace('YASS_Version', $remoteLastSeenVersions);
        $result = $this->_proxy('yass.getModifieds', $remoteLastSeenVersions);
        YASS_Proxy::decodeAllInplace('YASS_SyncState', $result);
        return $result;
    }
    
    /**
     * Determine the sync state of a particular entity
     *
     * @param $entityGuids array(entityGuid)
     * @return array(entityGuid => YASS_SyncState)
     */
    function getSyncStates($entityGuids) {
        $result = $this->_proxy('yass.getSyncStates', $entityGuids);
        YASS_Proxy::decodeAllInplace('YASS_SyncState', $result);
        return $result;
    }
    
    /**
     * Set the sync states of several entities
     *
     * @param $states array(entityGuid => YASS_Version)
     */
    function setSyncStates($states) {
        YASS_Proxy::encodeAllInplace('YASS_Version', $states);
        $result = $this->_proxy('yass.setSyncStates', $states);
    }
    
    /**
     * Obtain the next available version number
     *
     * @return YASS_Version
     */
    function tick() {
        throw new Exception('Not implemented: YASS_SyncStore_Proxy::tick()');
    }
    
    /**
     * Forcibly increment the versions of entities to make the current replica appear newest
     */
    function updateAllVersions() {
        $result = $this->_proxy('yass.updateAllVersions');
    }
    
    /**
     * Destroy any last-seen or sync-state data
     */
    function destroy() {
        $result = $this->_proxy('yass.destroy');
    }
    
    /**
     * Find any unmapped entities and... map them...
     */
    function onValidateGuids(YASS_Replica $replica) {
        $result =  $this->_proxy('yass.validateGuids');
    }
}

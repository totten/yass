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

require_once 'YASS/Engine.php';
require_once 'YASS/DataStore/Local.php';
require_once 'YASS/Replica.php';

/**
 * A generic SQL-backed data store for which entities are stored with localized IDs
 */
class YASS_DataStore_LocalizedGenericSQL extends YASS_DataStore_Local {

    /**
     * 
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     */
    public function __construct(YASS_Replica $replica) {
        require_once 'YASS/LocalDataStore/GenericSQL.php';
        parent::__construct($replica, new YASS_LocalDataStore_GenericSQL($replica, FALSE));
    }
    
    /**
     * Put content directly in the data store, bypassing the synchronization system.
     * This creates an un-synchronized entity.
     *
     * @return int, local id
     * @deprecated
     *
    function nativePut($type, $data, $lid = FALSE) {
        if (!$lid) {
            return $this->localDataStore->insert($type, $data);
        } else {
            $this->localDataStore->insertUpdate($type, $lid, $data);
            return $lid;
        }
    }
    
    function onPreSync(YASS_Replica $replica) {
        // This implementation does a bad job of maintaining GUID mappings, so
        // we need to do validation before every sync.
        $this->onValidateGuids($replica);
    }
    
    function onValidateGuids(YASS_Replica $replica) {
        $this->localDataStore->onValidateGuids($replica);
    }
    */
}


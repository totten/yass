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
 * An an in-memory data store for which entities are stored with localized IDs
 */
class YASS_DataStore_LocalizedMemory extends YASS_DataStore_Local {

    /**
     * 
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     */
    public function __construct(YASS_Replica $replica) {
        require_once 'YASS/LocalDataStore/Memory.php';
        parent::__construct($replica, new YASS_LocalDataStore_Memory($replica));
    }
    
}


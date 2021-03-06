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

require_once 'YASS/Replica.php';

/**
 * This is a profile for replicas based on remote CiviCRM instances
 */
class YASS_Replica_CiviCRMProxy extends YASS_Replica {
    /**
     * Construct a replica based on saved configuration metadata
     *
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     *  - remoteSite: int or "#local"
     *  - remoteReplica: string, replica name
     *  - site: array
     */
    function __construct($replicaSpec) {
        $mandates = array(
            'datastore' => 'Proxy',
            'syncstore' => 'Proxy',
            'guid_mapper' => 'Proxy',
        );
        $replicaSpec = array_merge($replicaSpec, $mandates);
        if (empty($replicaSpec['remoteSite']) || empty($replicaSpec['remoteReplica'])) {
            throw new Exception('Missing remoteSite or remoteReplica field');
        }
        parent::__construct($replicaSpec);
    }
    
    protected function createConflictListeners() {
        require_once 'YASS/ConflictListener/LogEntity.php';
        $result = parent::createConflictListeners();
        $result[] = new YASS_ConflictListener_LogEntity(array(
        ));
        return $result;
    }
    
    protected function createFilters() {
        $filters = parent::createFilters();
        
        require_once 'YASS/Filter/StdColumns.php';    
        $filters[] = new YASS_Filter_StdColumns(array(
          'weight' => 10,
        ));
        
        return $filters;
    }
}

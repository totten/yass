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

require_once 'YASS/Replica/CiviCRMMaster.php';

/**
 * This is a profile for replicas which aggregate data from many CiviCRM instances
 */
class YASS_Replica_ARMSMaster extends YASS_Replica_CiviCRMMaster {
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
            'access_control' => TRUE,
        );
        $replicaSpec = array_merge($replicaSpec, $mandates);
        parent::__construct($replicaSpec);
    }
        
    protected function createFilters() {
        // FIXME Get entity types from schema or configuration
        $syncableEntityTypes = array( // subset of YASS_Schema_CiviCRM::getEntityTypes / YASS_Schema_CiviCRM::$_ENTITIES
            'civicrm_contact', 'civicrm_address', 'civicrm_phone', 'civicrm_email',
            // 'civicrm_activity', 'civicrm_activity_assignment', 'civicrm_activity_target',
            'yass_conflict', 'yass_mergelog',
        );
  
        $filters = parent::createFilters();
        require_once 'YASS/Filter/StdACL.php';
        $filters[] = new YASS_Filter_StdACL(array(
            'entityTypes' => $syncableEntityTypes,
            'sites' => arms_interlink_config(),
            'replicaIdsByName' => arms_util_array_combine_properties(YASS_Engine::singleton()->getReplicas(), 'name', 'id'),
            'weight' => 5,
        ));
        return $filters;
    }
}

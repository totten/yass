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
 * This is a profile for replicas which aggregate data from many CiviCRM instances
 */
class YASS_Replica_CiviCRMMaster extends YASS_Replica {
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
            'datastore' => 'GenericSQL',
            'syncstore' => 'GenericSQL',
            'is_logged' => TRUE,
        );
        $replicaSpec = array_merge($replicaSpec, $mandates);
        parent::__construct($replicaSpec);
    }
        
    protected function createFilters() {
        $filters = parent::createFilters();
        require_once 'YASS/Filter/MergeFields.php';
        $filters[] = new YASS_Filter_MergeFields(array(
            'entityTypes' => array('civicrm_contact'),
            'paths' => array(
                '#custom',
                '#unknown',
            ),
            'weight' => 10,
        ));
        $filters[] = new YASS_Filter_MergeFields(array(
            'entityTypes' => array('civicrm_contact', 'civicrm_activity', 'civicrm_email', 'civicrm_phone'),
            'paths' => array(
                '', // merge the root node
            ),
            'weight' => 10,
        ));
        require_once 'YASS/Filter/Archive.php';
        $filters[] = new YASS_Filter_Archive(array(
            'weight' => -999,
        ));
        return $filters;
    }
}

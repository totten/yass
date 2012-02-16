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
 * This is a profile for replicas based on CiviCRM
 *
 * Note: You can currently only have one CiviCRM-based replica within
 * the function/address space. There are two primary reasons:
 *
 * 1. The createSchema() assumes that CiviCRM is bootstrapped and uses that to locate schema.
 * 2. The datastore and syncstore issue SQL queries using Drupal's default query system -- they
 *    won't switch to different DBs.
 *
 * However, the architecture is generally intended to support using one process to
 * sync multiple CiviCRMs. This is why we through the effort of loading the schema
 * from XML rather than assuming that data services are available.
 */
class YASS_Replica_CiviCRM extends YASS_Replica {
    /**
     * Construct a replica based on saved configuration metadata
     *
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     */
    function __construct($replicaSpec) {
        $mandates = array(
            'datastore' => 'CiviCRM',
            'syncstore' => 'TriggeredSQL',
            'is_triggered' => TRUE,
        );
        $replicaSpec = array_merge($replicaSpec, $mandates);
        parent::__construct($replicaSpec);
    }
    
    protected function createConflictListeners() {
        require_once 'YASS/ConflictListener/LogEntity.php';
        $result = parent::createConflictListeners();
        $result[] = new YASS_ConflictListener_LogEntity(array());
        return $result;
    }
    
    /** 
     * Instantiate a schema descriptor
     *
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     * @return YASS_ISchema
     */
    protected function createSchema($replicaSpec) {
            require_once 'YASS/Schema/CiviCRM.php';
            require_once 'YASS/Schema/Hybrid.php';
            require_once 'YASS/Schema/YASS.php';
            
            civicrm_initialize();
            require_once 'CRM/Utils/System.php';
            list ($major, $minor, $other) = explode('.', CRM_Utils_System::version());
            $rootXmlFile = drupal_get_path('module', 'civicrm') . '/../xml/schema/Schema.xml';
            
            $civicrm = YASS_Schema_CiviCRM::instance($rootXmlFile, $major . '.' . $minor);
            $yass = YASS_Schema_YASS::instance();
            
            $this->listeners[] = $civicrm;
            $this->listeners[] = $yass;
            
            return new YASS_Schema_Hybrid(array(
                'civicrm' => $civicrm,
                'yass' => $yass,
            ));
    }
}

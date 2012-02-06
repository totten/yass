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
require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/Filter/Chain.php';
require_once 'YASS/IGuidMapper.php';
require_once 'YASS/ConflictListener/Chain.php';

/**
 * A activatable synchronization target, including a data store and sync store.
 */
class YASS_Replica extends YASS_ReplicaListener {

    /**
     * @var array{yass_replicas} Specification for the replica
     */
    var $spec;
    
    /**
     * @var string ^[a-zA-Z0-9\-_\.]+$
     */
    var $name;
    
    /**
     * @var int
     */
    var $id;

    /**
     * @var int, optional; when specified, new YASS_Versions are based on effectiveReplicaId instead of id
     */
    var $effectiveId;

    /**
     * Whether this replica has been joined into the network
     *
     * @var bool
     */
    var $isActive;

    /**
     * @var YASS_IDataStore
     */
    var $data;
    
    /**
     * @var YASS_ISyncStore
     */
    var $sync;
    
    /**
     * @var YASS_IGuidMapper or null
     */
    var $mapper;
    
    /**
     * @var YASS_Filter_Chain
     */
    var $filters;
    
    /**
     * @var YASS_IConflictListener
     */
    var $conflictListeners;
    
    /**
     * @var YASS_Schema
     */
    var $schema;
    
    /**
     * @var bool, whether access control is enabled
     */
    var $accessControl;
    
    /**
     * Construct a replica based on saved configuration metadata
     *
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     */
    function __construct($replicaSpec) {
        $this->spec = $replicaSpec;
        $this->name = $replicaSpec['name'];
        $this->id = $replicaSpec['id'];
        $this->effectiveId = $replicaSpec['effective_replica_id'];
        $this->isActive = $replicaSpec['is_active'];
        $this->accessControl = $replicaSpec['access_control'];
        $this->schema = $this->_createSchema($replicaSpec);
        $this->mapper = $this->_createGuidMapper($replicaSpec);
        $this->data = $this->_createDatastore($replicaSpec);
        $this->sync = $this->_createSyncstore($replicaSpec);
        $this->conflictListeners = new YASS_ConflictListener_Chain(array(
            'listeners' => array(),
        ));
        $this->filters = new YASS_Filter_Chain(array(
            'filters' => module_invoke_all('yass_replica', array('op' => 'buildFilters', 'replica' => $this)),
        ));
    }
    
    function addFilter(YASS_Filter $filter) {
        return $this->filters->addFilter($filter);
    }
    
    function getEffectiveId() {
        return ($this->effectiveId ? $this->effectiveId : $this->id);
    }
    
    /**
     * Instantiate a sync store
     *
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     * @return YASS_ISyncStore
     */
    protected function _createSyncstore($replicaSpec) {
        switch ($replicaSpec['syncstore']) {
            // whitelist
            case 'CiviCRM':
            case 'LocalizedMemory':
            case 'Memory':
            case 'Proxy':
            case 'GenericSQL':
                require_once sprintf('YASS/SyncStore/%s.php', $replicaSpec['syncstore']);
                $class = new ReflectionClass('YASS_SyncStore_' . $replicaSpec['syncstore']);
                return $class->newInstance($this);
            default:
                return FALSE;
        }
    }
    
    /**
     * Instantiate a data store
     *
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     * @return YASS_IDataStore
     */
    protected function _createDatastore($replicaSpec) {
        switch ($replicaSpec['datastore']) {
            // whitelist
            case 'CiviCRM':
            case 'LocalizedMemory':
            case 'Memory':
            case 'Proxy':
            case 'GenericSQL':
                require_once sprintf('YASS/DataStore/%s.php', $replicaSpec['datastore']);
                $class = new ReflectionClass('YASS_DataStore_' . $replicaSpec['datastore']);
                return $class->newInstance($this);
            default:
                return FALSE;
        }
    }
    
    /** 
     * Instantiate a schema descriptor
     *
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     * @return YASS_Schema
     */
    protected function _createSchema($replicaSpec) {
        // FIXME naming
        if ($replicaSpec['datastore'] == 'CiviCRM') {
            require_once 'YASS/Schema/CiviCRM.php';
            require_once 'YASS/Schema/Hybrid.php';
            require_once 'YASS/Schema/YASS.php';
            return new YASS_Schema_Hybrid(array(
                'civicrm' => YASS_Schema_CiviCRM::instance('2.2'),
                'yass' => YASS_Schema_YASS::instance()
            ));
        } else {
            return FALSE;
        }
    }
    
    /**
     * Instantiate a GUID mapping service
     *
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     * @return YASS_IGuidMapper
     */
    protected function _createGuidMapper($replicaSpec) {
        $mapper = isset($replicaSpec['guid_mapper']) ? $replicaSpec['guid_mapper'] : 'GenericSQL';
        switch ($mapper) {
            // whitelist
            case 'GenericSQL':
            case 'Proxy':
                require_once sprintf('YASS/GuidMapper/%s.php', $mapper);
                $class = new ReflectionClass('YASS_GuidMapper_' . $mapper);
                return $class->newInstance($this);
            default:
                return FALSE;
        }
    }
     
}

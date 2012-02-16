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
require_once 'YASS/MergeLogs.php';
require_once 'YASS/ConflictListener/Chain.php';

/**
 * A activatable synchronization target, including a data store and sync store.
 */
class YASS_Replica extends YASS_ReplicaListener {

    public static function create($replicaSpec) {
        if (empty($replicaSpec['type'])) {
            $replicaSpec['type'] = 'Default';
        }
        switch ($replicaSpec['type']) {
            // whitelist
            case 'ARMSMaster':
            case 'ARMSProxy':
            case 'CiviCRM':
            case 'CiviCRMMaster':
            case 'CiviCRMProxy':
            case 'Master':
                require_once sprintf('YASS/Replica/%s.php', $replicaSpec['type']);
                $class = new ReflectionClass('YASS_Replica_' . $replicaSpec['type']);
                return $class->newInstance($replicaSpec);
            case 'Default':
                return new YASS_Replica($replicaSpec);
            default:
                return FALSE;
        }
    }

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
     * @var array(YASS_IReplicaListener)
     */
    var $listeners;
    
    /**
     * @var YASS_ISchema
     */
    var $schema;
    
    /**
     * @var bool, whether access control is enabled
     */
    var $accessControl;
    
    /**
     * @var YASS_MergeLogs
     */
    var $mergeLogs;
     
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
        $this->listeners = array();
        $this->listeners[] = $this;
        
        $this->schema = $this->createSchema($replicaSpec);
        
        $this->mapper = $this->createGuidMapper($replicaSpec);
        $this->listeners[] = $this->mapper; // FIXME self-registration
        
        $this->data = $this->createDatastore($replicaSpec);
        $this->listeners[] = $this->data; // FIXME self-registration
        
        $this->sync = $this->createSyncstore($replicaSpec);
        $this->listeners[] = $this->sync; // FIXME self-registration
        
        $this->mergeLogs = new YASS_MergeLogs();
        $this->conflictListeners = new YASS_ConflictListener_Chain(array(
            'listeners' => $this->createConflictListeners(),
        ));
        $this->filters = new YASS_Filter_Chain(array(
            'filters' => $this->createFilters(),
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
    protected function createSyncstore($replicaSpec) {
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
    protected function createDatastore($replicaSpec) {
        switch ($replicaSpec['datastore']) {
            // whitelist
            case 'CiviCRM':
            case 'LocalizedMemory':
            case 'LocalizedGenericSQL':
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
     * @return YASS_ISchema
     */
    protected function createSchema($replicaSpec) {
        return FALSE;
    }
    
    /**
     * Instantiate a GUID mapping service
     *
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     * @return YASS_IGuidMapper
     */
    protected function createGuidMapper($replicaSpec) {
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
    
    protected function createConflictListeners() {
        return array();
    }
    
    protected function createFilters() {
        return module_invoke_all('yass_replica', array('op' => 'buildFilters', 'replica' => $this));
    }
    
}

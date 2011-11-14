<?php

require_once 'YASS/ReplicaListener.php';
require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/GuidMapper.php';
require_once 'YASS/Schema/CiviCRM.php';

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
   * Whether this replica has been joined into the network
   *
   * @var bool
   */
  var $isActive;

  /**
   * @var YASS_DataStore
   */
  var $data;
  
  /**
   * @var YASS_ISyncStore
   */
  var $sync;
  
  /**
   * @var YASS_GuidMapper or null
   */
  var $mapper;
  
  /**
   * @var array(YASS_Filter), sorted by weight ascending
   */
  var $filters;
  
  /**
   * @var YASS_Schema
   */
  var $schema;
  
  /**
   * Construct a replica based on saved configuration metadata
   *
   * @param $replicaSpec array{yass_replicas} Specification for the replica
   */
  function __construct($replicaSpec) {
    $this->spec = $replicaSpec;
    $this->name = $replicaSpec['name'];
    $this->id = $replicaSpec['id'];
    $this->isActive = $replicaSpec['is_active'];
    $this->mapper = new YASS_GuidMapper($this);
    $this->data = $this->_createDatastore($replicaSpec);
    $this->sync = $this->_createSyncstore($replicaSpec);
    $this->schema = $this->_createSchema($replicaSpec);
    $this->filters = module_invoke_all('yass_replica', array('op' => 'buildFilters', 'replica' => $this));
    usort($this->filters, arms_util_sort_by('weight'));
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
      case 'ARMS':
      case 'LocalizedMemory':
      case 'Memory':
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
   * @return YASS_DataStore
   */
  protected function _createDatastore($replicaSpec) {
    switch ($replicaSpec['datastore']) {
      // whitelist
      case 'ARMS':
      case 'LocalizedMemory':
      case 'Memory':
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
    if ($replicaSpec['datastore'] == 'ARMS') {
      return YASS_Schema_CiviCRM::instance('2.2');
    } else {
      return FALSE;
    }
  }

}

<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/GuidMapper.php';
require_once 'YASS/IReplicaListener.php';

/**
 * A activatable synchronization target, including a data store and sync store.
 */
class YASS_Replica implements YASS_IReplicaListener {

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
   * @var YASS_SyncStore
   */
  var $sync;
  
  /**
   * @var YASS_GuidMapper or null
   */
  var $mapper;

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
    $this->data = $this->_createDatastore($replicaSpec);
    $this->sync = $this->_createSyncstore($replicaSpec);
    $this->mapper = new YASS_GuidMapper($replicaSpec);
  }
  
  /**
   * Instantiate a sync store
   *
   * @param $replicaSpec array{yass_replicas} Specification for the replica
   * @return YASS_SyncStore
   */
  protected function _createSyncstore($replicaSpec) {
    switch ($replicaSpec['syncstore']) {
      // whitelist
      case 'LocalizedMemory':
      case 'Memory':
      case 'GenericSQL':
        require_once sprintf('YASS/SyncStore/%s.php', $replicaSpec['syncstore']);
        $class = new ReflectionClass('YASS_SyncStore_' . $replicaSpec['syncstore']);
        return $class->newInstance($replicaSpec);
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
      case 'LocalizedMemory':
      case 'Memory':
      case 'GenericSQL':
        require_once sprintf('YASS/DataStore/%s.php', $replicaSpec['datastore']);
        $class = new ReflectionClass('YASS_DataStore_' . $replicaSpec['datastore']);
        return $class->newInstance($replicaSpec);
      default:
        return FALSE;
    }
  }

  function onPostJoin(YASS_Replica $replica, YASS_Replica $master) {}
  function onPostRejoin(YASS_Replica $replica, YASS_Replica $master) {}
  function onPostReset(YASS_Replica $replica, YASS_Replica $master) {}
  function onPostSync(YASS_Replica $replica) {}
  function onPreJoin(YASS_Replica $replica, YASS_Replica $master) {}
  function onPreRejoin(YASS_Replica $replica, YASS_Replica $master) {}
  function onPreReset(YASS_Replica $replica, YASS_Replica $master) {}
  function onPreSync(YASS_Replica $replica) {}
}

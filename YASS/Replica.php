<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';

/**
 * A activatable synchronization target, including a data store and sync store.
 */
class YASS_Replica {
  
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
   * Construct a replica based on saved configuration metadata
   *
   * @param $replicaSpec array{yass_replicas} Specification for the replica
   */
  function __construct($replicaSpec) {
    $this->name = $replicaSpec['name'];
    $this->id = $replicaSpec['id'];
    $this->isActive = $replicaSpec['is_active'];
    $this->data = $this->_createDatastore($replicaSpec);
    $this->sync = $this->_createSyncstore($replicaSpec);
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
      case 'Memory':
      case 'GenericSQL':
        require_once sprintf('YASS/DataStore/%s.php', $replicaSpec['datastore']);
        $class = new ReflectionClass('YASS_DataStore_' . $replicaSpec['datastore']);
        return $class->newInstance($replicaSpec);
      default:
        return FALSE;
    }
  }
    
  /**
   * Create/update a batch of entities
   *
   * @param $rows array(0 => guid, 1 => data)
   */
  function set($rows) {
    foreach ($rows as $row) {
      $entity = new YASS_Entity($row[0], $row[1]);
      $this->data->putEntity($entity);
      $this->sync->onUpdateEntity($entity->entityGuid);
    }
  }
  
  /**
   * Get the full state of an entity
   *
   * @return array(0 => replicaId, 1 => tick, 2 => data)
   */
  function get($guid) {
    $entity = $this->data->getEntity($guid);
    $syncState = $this->sync->getSyncState($guid);
    return array($syncState->modified->replicaId, $syncState->modified->tick, $entity->data);
  }

}

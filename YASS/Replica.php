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
   * Instantiate a sync store
   *
   * @param $metadata array{yass_replicas} Specification for the replica
   * @return YASS_SyncStore
   */
  protected function _createSyncstore($metadata) {
    switch ($metadata['syncstore']) {
      // whitelist
      case 'Memory':
      case 'GenericSQL':
        require_once sprintf('YASS/SyncStore/%s.php', $metadata['syncstore']);
        $class = new ReflectionClass('YASS_SyncStore_' . $metadata['syncstore']);
        return $class->newInstance($metadata);
      default:
        return FALSE;
    }
  }
  
  /**
   * Instantiate a data store
   *
   * @param $metadata array{yass_replicas} Specification for the replica
   * @return YASS_DataStore
   */
  protected function _createDatastore($metadata) {
    switch ($metadata['datastore']) {
      // whitelist
      case 'Memory':
      case 'GenericSQL':
        require_once sprintf('YASS/DataStore/%s.php', $metadata['datastore']);
        $class = new ReflectionClass('YASS_DataStore_' . $metadata['datastore']);
        return $class->newInstance($metadata);
      default:
        return FALSE;
    }
  }
}

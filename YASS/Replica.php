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
}

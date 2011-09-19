<?php

/**
 * Test synchronization service
 * 
 * Dependencies:
 * Drupal-SimpleTest 1.x
 */ 

require_once 'ARMS/Test.php';

class YASS_Test extends ARMS_Test {
  
  function setUp() {
    parent::setUp();
    require_once 'YASS/Engine.php';
    require_once 'YASS/Replica/Dummy.php';
    require_once 'YASS/ConflictResolver/Exception.php';
    require_once 'YASS/ConflictResolver/SrcWins.php';
  }

  function assertSyncState($replica, $entityType, $entityGuid, $replicaId, $tick, $data) {
    list ($actualReplicaId, $actualTick, $actualData) = $replica->get($entityType, $entityGuid);
    $this->assertEqual($replicaId, $actualReplicaId);
    $this->assertEqual($tick, $actualTick);
    $this->assertEqual($data, $actualData);
  }
}
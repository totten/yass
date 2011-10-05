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
  
  function dumpReplicas($replicas) {
    // last-seen status
    printf("LAST SEEN\n");
    foreach ($replicas as $replica) {
      printf("%25s: ", $replica->sync->replicaId);
      foreach ($replica->sync->lastSeen as $lastSeen) {
        printf(" (%s,%d)", $lastSeen->replicaId, $lastSeen->tick);
      }
      print "\n";
    }
    printf("\n");

    // content and syncstate    
    $allEntities = array(); // array(entityType => array(entityGuid))
    foreach ($replicas as $replica) {
      foreach ($replica->data->entities as $type => $entities) {
        if (!isset($allEntities[$type])) {
          $allEntities[$type] = array();
        }
        $allEntities[$type] = array_unique(array_merge(array_keys($entities), $allEntities[$type]));
      }
    }
    
    foreach ($allEntities as $type => $entities) {
      foreach ($entities as $guid) {
        printf("ENTITY: %10s %10s\n", $type, $guid);
        foreach ($replicas as $replica) {
          $entity = $replica->data->getEntity($type, $guid);
          $syncState = $replica->sync->getSyncState($type, $guid);
          $versionString = sprintf("(%s,%d)", $syncState->modified->replicaId, $syncState->modified->tick);
          printf("%25s: %25s \"%s\"\n", $replica->sync->replicaId, $versionString, $entity->data);
        }
        print "\n";
      }
    }
  }
}
<?php

/**
 * Test synchronization service
 * 
 * Dependencies:
 * Drupal-SimpleTest 1.x
 */ 

require_once 'ARMS/Test.php';

class YASS_Test extends ARMS_Test {
  const TESTENTITY = 'testentity';
  
  function setUp() {
    parent::setUp();
    require_once 'YASS/Engine.php';
    require_once 'YASS/Replica/Dummy.php';
    require_once 'YASS/ConflictResolver/Exception.php';
    require_once 'YASS/ConflictResolver/SrcWins.php';
    require_once 'YASS/ConflictResolver/DstWins.php';
    require_once 'YASS/ConflictResolver/Queue.php';
    YASS_Engine::destroyReplicas();
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
      $lastSeens = $replica->sync->getLastSeenVersions();
      ksort($lastSeens);
      foreach ($lastSeens as $lastSeen) {
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
  
  /**
   * Run a series of update and sync operations with a single test entity.
   *
   * @param $sentence string, a list of space-delimited tasks. See _eval() for a description of task language
   * @param $convergentValues array (entityGuid=>string), the final values to which all replicas converge after evaluating the sentence
   */
  function _runSentenceTest($sentence, $convergentValues) {
    YASS_Engine::destroyReplicas();
    $this->_eval('master,r1,r2,r3:init *:sync ' . $sentence . ' *:sync *:sync');
    $replicas = YASS_Engine::getReplicas();
        
    // printf("SENTENCE: %s\n", $sentence);
    // $this->dumpReplicas($replicas);
    
    foreach ($replicas as $replicaId => $replica) {
      foreach ($convergentValues as $entityGuid => $convergentValue) {
        list ($actualReplicaId, $actualTick, $actualData) = $replica->get(self::TESTENTITY, $entityGuid);
        $this->assertEqual($convergentValue, $actualData, sprintf('expected="%s" actual="%s"', $convergentValue, $actualData));
      }
    }
  }

  /**
   * Run a series of update and sync operations
   *
   * @param $sentence string, a list of space-delimited tasks; valid tasks are:
   *   - "$REPLICA:init": add an empty dummy replica
   *   - "$REPLICA:init:$DATASTORE,$SYNCSTORE": add an empty dummy replica
   *   - "$REPLICA:add:$ENTITY": add a new entity on the replica
   *   - "$REPLICA:modify:$ENTITY": modify the content of the entity on the replica
   *   - "$REPLICA:sync": sync the replica with the master; if a conflict arises, throw an exception
   *   - "$REPLICA:sync:SrcWins": sync the replica with the master; a conflict is expected and will be resolved with SrcWins
   *
   * Note that $REPLICA may be a single replica name, a comma-delimited list, or a wildcard ('*')
   */
  function _eval($sentence) {
    arms_util_include_api('array');
    $replicas = YASS_Engine::getReplicas();
    $updates = array(); // array(entityGuid => array(replicaName => int))
    foreach (explode(' ', $sentence) as $task) {
      list ($targetReplicaCode,$action,$opt) = explode(':', $task);
      $targetReplicaNames = ($targetReplicaCode == '*') ? array_diff(arms_util_array_collect($replicas, 'name'),array('master')) : explode(',', $targetReplicaCode);
      foreach ($targetReplicaNames as $replicaName) {
        switch ($action) {
          case 'init':
            $metadata = array('name' => $replicaName);
            if (!empty($opt)) {
              list ($metadata['datastore'],$metadata['syncstore']) = explode(',', $opt);
            }
            YASS_Engine::addReplica(new YASS_Replica_Dummy($metadata));
            $replicas = YASS_Engine::getReplicas();
            break;
          case 'add':
            $updates[$opt][$replicaName] = 1;
            YASS_Engine::getReplicaByName($replicaName)->set(array(
              array(self::TESTENTITY, $opt, sprintf('%s.%d from %s', $opt, $updates[$opt][$replicaName], $replicaName)),
            ));
            break;
          case 'modify':
            $updates[$opt][$replicaName] = 1+(empty($updates[$opt][$replicaName]) ? 0 : $updates[$opt][$replicaName]);
            YASS_Engine::getReplicaByName($replicaName)->set(array(
              array(self::TESTENTITY, $opt, sprintf('%s.%d from %s', $opt, $updates[$opt][$replicaName], $replicaName)),
            ));
            break;
          case 'sync':
            if (empty($opt)) {
              $conflictResolver = new YASS_ConflictResolver_Exception();
              YASS_Engine::bidir(YASS_Engine::getReplicaByName($replicaName), YASS_Engine::getReplicaByName('master'), $conflictResolver);
            } else {
              $class = new ReflectionClass('YASS_ConflictResolver_' . $opt);
              $conflictResolver = new YASS_ConflictResolver_Queue(array($class->newInstance()));
              YASS_Engine::bidir(YASS_Engine::getReplicaByName($replicaName), YASS_Engine::getReplicaByName('master'), $conflictResolver);
              $this->assertTrue($conflictResolver->isEmpty(), 'A conflict resolver was specified but no conflict arose');
            }
            break;
          default:
            $this->fail('Unrecognized task: ' . $task);
        }
      }
    }
  }
}

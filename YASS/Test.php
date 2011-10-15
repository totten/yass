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
  private $_replicaDefaults;
  
  function setUp() {
    parent::setUp();
    require_once 'YASS/Engine.php';
    require_once 'YASS/Replica.php';
    require_once 'YASS/ConflictResolver/Exception.php';
    require_once 'YASS/ConflictResolver/SrcWins.php';
    require_once 'YASS/ConflictResolver/DstWins.php';
    require_once 'YASS/ConflictResolver/Queue.php';
    YASS_Engine::singleton()->destroyReplicas();
    YASS_Engine::singleton(TRUE);
    $this->setReplicaDefaults(array('datastore' => 'Memory', 'syncstore' => 'Memory', 'is_active' => TRUE));
  }

  function assertSyncState($replica, $entityGuid, $replicaId, $tick, $data, $entityType = self::TESTENTITY) {
    $actualEntity = $replica->data->getEntity($entityGuid);
    $actualSyncState = $replica->sync->getSyncState($entityGuid);
    $this->assertEqual($replicaId, $actualSyncState->modified->replicaId, sprintf("replicaId: expected=[%s] actual=[%s]", $replicaId, $actualSyncState->modified->replicaId));
    $this->assertEqual($tick, $actualSyncState->modified->tick, sprintf("tick: expected=[%s] actual=[%s]", $tick, $actualSyncState->modified->tick));
    $this->assertEqual($data, $actualEntity->data, sprintf("data: expected=[%s] actual=[%s]", $data, $actualEntity->data));
    $this->assertEqual($entityType, $actualEntity->entityType, sprintf("entityType: expected=[%s] actual=[%s]", $entityType, $actualEntity->entityType));
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
    $allEntities = array(); // array(entityGuid)
    foreach ($replicas as $replica) {
      $entities = $replica->data->getAllEntitiesDebug();
      $allEntities = array_unique(array_merge(array_keys($entities), $allEntities));
    }
    
    foreach ($allEntities as $guid) {
        printf("ENTITY: %10s\n", $guid);
        foreach ($replicas as $replica) {
          $entity = $replica->data->getEntity($guid);
          $syncState = $replica->sync->getSyncState($guid);
          $versionString = sprintf("(%s,%d)", $syncState->modified->replicaId, $syncState->modified->tick);
          printf("%25s: %25s \"%s\"\n", $replica->sync->replicaId, $versionString, $entity->data);
        }
        print "\n";
    }
  }
  
  /**
   * Run a series of update and sync operations with a single test entity.
   *
   * @param $sentence string, a list of space-delimited tasks. See _eval() for a description of task language
   * @param $convergentValues array (entityGuid=>string), the final values to which all replicas converge after evaluating the sentence
   */
  function _runSentenceTest($sentence, $convergentValues) {
    YASS_Engine::singleton()->destroyReplicas();
    $this->_eval('master,r1,r2,r3:init *:sync ' . $sentence . ' engine:syncAll');
    $replicas = YASS_Engine::singleton()->getReplicas();
        
    // printf("SENTENCE: %s\n", $sentence);
    // $this->dumpReplicas($replicas);
    
    foreach ($replicas as $replicaId => $replica) {
      foreach ($convergentValues as $entityGuid => $convergentValue) {
        $actualEntity = $replica->data->getEntity($entityGuid);
        $this->assertEqual(self::TESTENTITY, $actualEntity->entityType);
        $this->assertEqual($convergentValue, $actualEntity->data, sprintf('data: expected="%s" actual="%s"', $convergentValue, $actualEntity->data));
      }
    }
  }

  /**
   * Run a series of update and sync operations
   *
   * @param $sentence string, a list of space-delimited tasks; valid tasks are:
   *   - "engine:flush": flush in-memory knowledge about all replicas
   *   - "engine:destroy": flush in-memory and persistent knowledge about all replicas
   *   - "engine:syncAll": synchronize everything with the engine's syncAll algorithm
   *   - "$REPLICA:init": add an empty dummy replica
   *   - "$REPLICA:init:$DATASTORE,$SYNCSTORE": add an empty dummy replica
   *   - "$REPLICA:add:$ENTITY": add a new entity on the replica
   *   - "$REPLICA:modify:$ENTITY": modify the content of the entity on the replica
   *   - "$REPLICA:sync": sync the replica with the master; if a conflict arises, throw an exception
   *   - "$REPLICA:sync:SrcWins": sync the replica with the master; a conflict is expected and will be resolved with SrcWins
   *   - "$REPLICA:destroy": destroy a replica and its syncstore/datastore
   *
   * Note that $REPLICA may be a single replica name, a comma-delimited list, or a wildcard ('*')
   */
  function _eval($sentence) {
    arms_util_include_api('array');
    $replicas = YASS_Engine::singleton()->getReplicas();
    $updates = array(); // array(entityGuid => array(replicaName => int))
    foreach (explode(' ', $sentence) as $task) {
      list ($targetReplicaCode,$action,$opt) = explode(':', $task);
      
      if ($targetReplicaCode == 'engine') {
        switch($action) {
          case 'flush':
            YASS_Engine::singleton(TRUE);
            break;
          case 'destroy':
            YASS_Engine::singleton()->destroyReplicas();
            YASS_Engine::singleton(TRUE);
            break;
          case 'syncAll':
            if (empty($opt)) {
              $conflictResolver = new YASS_ConflictResolver_Exception();
              YASS_Engine::singleton()->syncAll( YASS_Engine::singleton()->getReplicaByName('master'), $conflictResolver );
            } else {
              $class = new ReflectionClass('YASS_ConflictResolver_' . $opt);
              $conflictResolver = new YASS_ConflictResolver_Queue(array($class->newInstance()));
              YASS_Engine::singleton()->syncAll( YASS_Engine::singleton()->getReplicaByName('master'), $conflictResolver );
              $this->assertTrue($conflictResolver->isEmpty(), 'A conflict resolver was specified but no conflict arose');
            }
            break;
          default:
            $this->fail('Unrecognized task: ' . $task);
        }
        continue;
      }
      
      $targetReplicaNames = ($targetReplicaCode == '*') ? array_diff(arms_util_array_collect($replicas, 'name'),array('master')) : explode(',', $targetReplicaCode);
      foreach ($targetReplicaNames as $replicaName) {
        switch ($action) {
          case 'init':
            $replicaSpec = array('name' => $replicaName);
            if (!empty($opt)) {
              list ($replicaSpec['datastore'],$replicaSpec['syncstore']) = explode(',', $opt);
            }
            $this->createReplica($replicaSpec);
            $replicas = YASS_Engine::singleton()->getReplicas();
            break;
          case 'add':
            $updates[$opt][$replicaName] = 1;
            $this->updateEntities(YASS_Engine::singleton()->getReplicaByName($replicaName), array(
              array('guid' => $opt, 'type' => self::TESTENTITY, 'data' => sprintf('%s.%d from %s', $opt, $updates[$opt][$replicaName], $replicaName)),
            ));
            break;
          case 'modify':
            $updates[$opt][$replicaName] = 1+(empty($updates[$opt][$replicaName]) ? 0 : $updates[$opt][$replicaName]);
            $this->updateEntities(YASS_Engine::singleton()->getReplicaByName($replicaName), array(
              array('guid' => $opt, 'type' => self::TESTENTITY, 'data' => sprintf('%s.%d from %s', $opt, $updates[$opt][$replicaName], $replicaName)),
            ));
            break;
          case 'sync':
            if (empty($opt)) {
              $conflictResolver = new YASS_ConflictResolver_Exception();
              YASS_Engine::singleton()->bidir(YASS_Engine::singleton()->getReplicaByName($replicaName), YASS_Engine::singleton()->getReplicaByName('master'), $conflictResolver);
            } else {
              $class = new ReflectionClass('YASS_ConflictResolver_' . $opt);
              $conflictResolver = new YASS_ConflictResolver_Queue(array($class->newInstance()));
              YASS_Engine::singleton()->bidir(YASS_Engine::singleton()->getReplicaByName($replicaName), YASS_Engine::singleton()->getReplicaByName('master'), $conflictResolver);
              $this->assertTrue($conflictResolver->isEmpty(), 'A conflict resolver was specified but no conflict arose');
            }
            break;
          case 'destroy':
            YASS_Engine::singleton()->destroyReplica(YASS_Engine::singleton()->getReplicaByName($replicaName));
            break;
          default:
            $this->fail('Unrecognized task: ' . $task);
        }
      }
    }
  }
  
  function setReplicaDefaults($defaults) {
    $this->_replicaDefaults = $defaults;
  }
  
  function createReplica($replicaSpec) {
    $replicaSpec = array_merge($this->_replicaDefaults, $replicaSpec);
    YASS_Engine::singleton()->setReplicaSpec($replicaSpec);
    return YASS_Engine::singleton()->getReplicaByName($replicaSpec['name']);
  }
    
  /**
   * Simulate updates to multiple entities -- as if the updates had been performed on the replica.
   *
   * @param $rows array(array('guid' => guid, 'type' => type, 'data' => data))
   */
  function updateEntities($replica, $rows) {
    foreach ($rows as $row) {
      $entity = new YASS_Entity($row['guid'], $row['type'], $row['data']);
      $replica->data->putEntity($entity);
      $replica->sync->onUpdateEntity($entity->entityGuid);
    }
  }
}

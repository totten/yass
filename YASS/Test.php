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
    private $_evaluatorTemplate;
    
    function setUp() {
        parent::setUp();
        require_once 'YASS/Engine.php';
        require_once 'YASS/Context.php';
        require_once 'YASS/Replica.php';
        require_once 'YASS/ConflictResolver/Exception.php';
        require_once 'YASS/ConflictResolver/SrcWins.php';
        require_once 'YASS/ConflictResolver/DstWins.php';
        require_once 'YASS/ConflictResolver/Queue.php';
        require_once 'YASS/Test/Evaluator.php';
        require_once 'YASS/Test/StringEntityEvaluator.php';
        YASS_Engine::singleton()->destroyReplicas();
        YASS_Engine::singleton(TRUE);
        YASS_Context::reset();
        $this->setReplicaDefaults(array('datastore' => 'Memory', 'syncstore' => 'Memory', 'is_active' => TRUE));
        $this->setEvaluatorTemplate(new YASS_Test_StringEntityEvaluator($this));
    }
    
    /**
     * Assert that the given replicas contain exactly the given data items
     *
     * @param $replicas array(YASS_Replica)
     * @param $expecteds array(entityData) list of expected data values
     */
    function assertAllData($replicas, $expecteds) {
        arms_util_include_api('array');
        sort($expecteds);
        foreach ($replicas as $replica) {
            $actuals = arms_util_array_collect($replica->data->getAllEntitiesDebug(), 'data');
            sort($actuals);
            $this->assertEqual($expecteds, $actuals, sprintf("expected=[%s] actual=[%s]", implode(' ', $expecteds), implode(' ', $actuals)));
        }
    }

    function assertSyncState($replica, $entityGuid, $replicaId, $tick, $data, $entityType = self::TESTENTITY) {
        $actualEntities = $replica->data->getEntities(array($entityGuid));
        $actualSyncStates = $replica->sync->getSyncStates(array($entityGuid));
        $this->assertEqual($replicaId, $actualSyncStates[$entityGuid]->modified->replicaId, sprintf("replicaId: expected=[%s] actual=[%s]", $replicaId, $actualSyncStates[$entityGuid]->modified->replicaId));
        $this->assertEqual($tick, $actualSyncStates[$entityGuid]->modified->tick, sprintf("tick: expected=[%s] actual=[%s]", $tick, $actualSyncStates[$entityGuid]->modified->tick));
        $this->assertEqual($data, $actualEntities[$entityGuid]->data, sprintf("data: expected=[%s] actual=[%s]", $data, $actualEntities[$entityGuid]->data));
        $this->assertEqual($entityType, $actualEntities[$entityGuid]->entityType, sprintf("entityType: expected=[%s] actual=[%s]", $entityType, $actualEntities[$entityGuid]->entityType));
    }
    
    function dumpReplicas($replicas) {
        $ctx = new YASS_Context(array(
            'disableAccessControl' => TRUE,
        ));
        printf("------------------------------------------------------------------------\n");
        $names = arms_util_array_combine_properties($replicas, 'id', 'name');
        
        // last-seen status
        printf("LAST SEEN\n");
        foreach ($replicas as $replica) {
            printf("%25s: ", $replica->name);
            $lastSeens = $replica->sync->getLastSeenVersions();
            ksort($lastSeens);
            foreach ($lastSeens as $lastSeen) {
                printf(" (%s,%d)", $names[$lastSeen->replicaId] ? $names[$lastSeen->replicaId] : ('#'.$lastSeen->replicaId), $lastSeen->tick);
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
                    $entities = $replica->data->getEntities(array($guid));
                    $syncStates = $replica->sync->getSyncStates(array($guid));
                    if ($syncStates[$guid]) {
                        $versionString = sprintf("(%s,%s)", $names[$syncStates[$guid]->modified->replicaId] ? $names[$syncStates[$guid]->modified->replicaId] : ('#'.$syncStates[$guid]->modified->replicaId), $syncStates[$guid]->modified->tick);
                    } else {
                        $versionString = '(na,na)';
                    }
                    if (is_array($entities[$guid]->data) || is_object($entities[$guid]->data)) {
                        printf("%25s: %25s %s=%s\n", $replica->name, $versionString, $entities[$guid]->entityType, substr(json_encode($entities[$guid]->data), 0, 180));
                    } else {
                        printf("%25s: %25s %s=\"%s\"\n", $replica->name, $versionString, $entities[$guid]->entityType, $entities[$guid]->data);
                    }
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
        $replicas = YASS_Engine::singleton()->getActiveReplicas();
                
        // printf("SENTENCE: %s\n", $sentence);
        // $this->dumpReplicas($replicas);
        
        foreach ($replicas as $replicaId => $replica) {
            foreach ($convergentValues as $entityGuid => $convergentValue) {
                $actualEntities = $replica->data->getEntities(array($entityGuid));
                $this->assertEqual(self::TESTENTITY, $actualEntities[$entityGuid]->entityType);
                $this->assertEqual($convergentValue, $actualEntities[$entityGuid]->data, sprintf('data: expected="%s" actual="%s"', $convergentValue, $actualEntities[$entityGuid]->data));
            }
        }
    }

    /**
     * Run a series of update and sync operations
     *
     * @param $sentence string, a list of space-delimited tasks; valid tasks are:
     *   - "engine:flush": flush in-memory knowledge about all replicas
     *   - "engine:destroy": flush in-memory and persistent knowledge about all replicas
     *   - "engine:dump": print contents of every registered replica
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
        $evaluator = clone $this->_evaluatorTemplate;
        $ctx = new YASS_Context(array(
            'testSentence' => $sentence,
            'testEvaluator' => $evaluator,
        ));
        
        $sentence = $sentence . "\n";
        $sentence = preg_replace("/\/\/.*\n/sU", " ", $sentence);   
        $sentence = preg_replace("/\/\*.*\*\//sU", " ", $sentence);
        $sentence = trim(preg_replace("/[\r\n\t ]+/", " ", $sentence));
        foreach (explode(' ', $sentence) as $task) {
            $evaluator->evaluate($task);
        }
    }
    
    function setEvaluatorTemplate($evaluatorTemplate) {
        $this->_evaluatorTemplate = $evaluatorTemplate;
    }
    
    function setReplicaDefaults($defaults) {
        $this->_replicaDefaults = $defaults;
    }
    
    function createReplica($replicaSpec) {
        $replicaSpec = array_merge($this->_replicaDefaults, $replicaSpec);
        return YASS_Engine::singleton()->createReplica($replicaSpec);
    }
        
    /**
     * Simulate updates to multiple entities -- as if the updates had been performed on the replica.
     *
     * @param $rows array(array('guid' => guid, 'type' => type, 'data' => data))
     */
    function updateEntities(YASS_Replica $replica, $rows) {
        foreach ($rows as $row) {
            if (!array_key_exists('exists', $row)) {
                $row['exists'] = TRUE;
            }
            $entity = new YASS_Entity($row['guid'], $row['type'], $row['data'], $row['exists']);
            $replica->data->putEntities(array($entity));
            $replica->sync->onUpdateEntity($entity->entityGuid);
        }
    }
}

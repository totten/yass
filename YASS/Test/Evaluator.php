<?php

/**
 * A helper which evaluates statements in the testing DSL.
 *
 * There are two techniques for mapping DSL statements to method calls:
 * - If the subject of the statement is the keyword 'engine', then the behavior is special. 
 *   Ex: "engine:someaction:arg1:arg2"
 *       => "$evaluator->engine_someaction('arg1', 'arg2')"
 * - Otherwise, the subject of the statement is assumed to be a replica name, and it's
 *   passed as an argument.
 *   Ex: "r1:someaction:arg1:arg2"
 *       => "$evaluator->someaction('r1', 'arg1', 'arg2')"
 *   Ex: "r1,r2,r3:someaction:arg1:arg2"
 *       => "$evaluator->someaction('r1', 'arg1', 'arg2')"
 *       => "$evaluator->someaction('r2', 'arg1', 'arg2')"
 *       => "$evaluator->someaction('r3', 'arg1', 'arg2')"
 */
class YASS_Test_Evaluator {

    function __construct(YASS_Test $test) {
        $this->test = $test;
        $this->updates = array(); // array(entityGuid => array(replicaName => int))
        arms_util_include_api('array');
    }
    
    /**
     * API entry point
     *
     * @param $task an individual task to execute, e.g. "r1:add:e1"
     * @return void
     */
    function evaluate($task) {
        arms_util_include_api('array');
        $ctx = new YASS_Context(array(
            'testTask' => $task,
        ));
        
        $taskParts = explode(':', $task);
        $targetReplicaCode = $taskParts[0];
        $action = $taskParts[1];
            
        if ($targetReplicaCode == 'engine') {
            $callback = array($this, 'engine_' . $action);
            if (is_callable($callback)) {
                call_user_func_array($callback, array_slice($taskParts, 2));
            } else {
                $this->test->fail('Unrecognized task: ' . $task);
            }
            return;
        }

        if ($targetReplicaCode == '*') {
            $replicas = YASS_Engine::singleton()->getActiveReplicas();
            $targetReplicaNames = array_diff(arms_util_array_collect($replicas, 'name'),array('master'));
        } else {
            $targetReplicaNames = explode(',', $targetReplicaCode);
        }

        foreach ($targetReplicaNames as $replicaName) {
            $callback = array($this, $action);
            if (is_callable($callback)) {
                $args = array_slice($taskParts, 2);
                array_unshift($args, $replicaName);
                call_user_func_array($callback, $args);
            } else {
                $this->test->fail('Unrecognized task: ' . $task);
            }
        }
    }
    
    /**
     * Flush cached, in-memory replica descriptors
     */
    function engine_flush() {
        YASS_Engine::singleton(TRUE);
    }
    
    /**
     * Destroy all data in all replicas
     */
    function engine_destroy() {
        YASS_Engine::singleton()->destroyReplicas();
        YASS_Engine::singleton(TRUE);
    }
    
    /**
     * Print details of all replicas to the console
     */
    function engine_dump() {
        $replicas = YASS_Engine::singleton()->getActiveReplicas();
        $this->test->dumpReplicas($replicas);
    }
    
    /**
     * Synchronize all replicas with the master
     */
    function engine_syncAll($opt = NULL) {
        if (empty($opt)) {
            $conflictResolver = new YASS_ConflictResolver_Exception();
            YASS_Engine::singleton()->syncAll( YASS_Engine::singleton()->getReplicaByName('master'), $conflictResolver );
        } else {
            $class = new ReflectionClass('YASS_ConflictResolver_' . $opt);
            $conflictResolver = new YASS_ConflictResolver_Queue(array($class->newInstance()));
            YASS_Engine::singleton()->syncAll( YASS_Engine::singleton()->getReplicaByName('master'), $conflictResolver );
            $this->test->assertTrue($conflictResolver->isEmpty(), 'A conflict resolver was specified but no conflict arose');
        }
    }
    
    /**
     * Initialize a new replica
     */
    function init($replicaName, $opt = NULL) {
        $replicaSpec = array('name' => $replicaName);
        if (!empty($opt)) {
            list ($replicaSpec['datastore'],$replicaSpec['syncstore']) = explode(',', $opt);
        }
        $this->test->createReplica($replicaSpec);
        $replicas = YASS_Engine::singleton()->getActiveReplicas(); // FIXME
    }
    
    /**
     * Synchronize a replica with the master
     */
    function sync($replicaName, $resolverName = NULL) {
        if (empty($resolverName)) {
            $conflictResolver = new YASS_ConflictResolver_Exception();
            YASS_Engine::singleton()->bidir(YASS_Engine::singleton()->getReplicaByName($replicaName), YASS_Engine::singleton()->getReplicaByName('master'), $conflictResolver);
        } else {
            $class = new ReflectionClass('YASS_ConflictResolver_' . $resolverName);
            $conflictResolver = new YASS_ConflictResolver_Queue(array($class->newInstance()));
            YASS_Engine::singleton()->bidir(YASS_Engine::singleton()->getReplicaByName($replicaName), YASS_Engine::singleton()->getReplicaByName('master'), $conflictResolver);
            $this->test->assertTrue($conflictResolver->isEmpty(), 'A conflict resolver was specified but no conflict arose');
        }
    }
    
    /**
     * Destroy a replica and all its content
     */
    function destroy($replicaName) {
        YASS_Engine::singleton()->destroyReplica(YASS_Engine::singleton()->getReplicaByName($replicaName));
    }
    
    /**
     * Assert that a replica exists
     */
    function exists($replicaName) {
        $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
        $this->test->assertTrue($replica instanceof YASS_Replica,
            sprintf('Replica "%s" should exist [[Running "%s" in "%s"]]', $replicaName, $entityGuid, YASS_Context::get('testTask'), YASS_Context::get('testSentence')));
    }
    
    /**
     * Assert that a replica does not exist
     */
    function existsNot($replicaName) {
        $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
        $this->test->assertTrue($replica === FALSE,
            sprintf('Replica "%s" should not exist [[Running "%s" in "%s"]]', $replicaName, $entityGuid, YASS_Context::get('testTask'), YASS_Context::get('testSentence')));
    }
    
    /**
     * Assert that a replica has a given entity
     */
    function has($replicaName, $entityGuid) {
        $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
        $entities = $replica->data->getEntities(array($entityGuid));
        $this->test->assertTrue(!empty($entities[$entityGuid]) && $entities[$entityGuid]->exists, 
            sprintf('Replica "%s" should have entity "%s" [[Running "%s" in "%s"]]', $replicaName, $entityGuid, YASS_Context::get('testTask'), YASS_Context::get('testSentence')));
    }
    
    /**
     * Assert that a replica does not have a given entity
     */
    function hasNot($replicaName, $entityGuid) {
        $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
        $entities = $replica->data->getEntities(array($entityGuid));
        $this->test->assertTrue(empty($entities[$entityGuid]) || !$entities[$entityGuid]->exists, 
            sprintf('Replica "%s" should not have entity "%s" [[Running "%s" in "%s"]]', $replicaName, $entityGuid, YASS_Context::get('testTask'), YASS_Context::get('testSentence')));
    }
    
    /**
     * Submit all data from replica to master, overwriting discrepancies in the master. Relies on existing ID-GUID mappings.
     */
    function rejoin($replicaName) {
        $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
        $master = YASS_Engine::singleton()->getReplicaByName('master');
        YASS_Engine::singleton()->rejoin($replica, $master);
        YASS_Engine::singleton(TRUE);
    }
    
    /**
     * Submit all data from master to replica, overwriting discrepancies in the replica. Relies on existing ID-GUID mappings.
     */
    function reset($replicaName) {
        $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
        $master = YASS_Engine::singleton()->getReplicaByName('master');
        YASS_Engine::singleton()->reset($replica, $master);
        YASS_Engine::singleton(TRUE);
    }

}

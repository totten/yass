<?php

require_once 'YASS/Test/Evaluator.php';

/**
 * A helper which evaluates statements in the testing DSL. This variant creates
 * string-based entities using a simple pattern.
 *
 * For example, if replica "r1" adds an entity "e", then the entity's content
 * will initilly be "e.1 from r1". Subsequent modifications will change it to
 * "e.2 from r1", "e.3 from r1", etc.
 */
class YASS_Test_StringEntityEvaluator extends YASS_Test_Evaluator {

  function __construct(YASS_Test $test) {
    parent::__construct($test);
    $this->updates = array(); // array(entityGuid => array(replicaName => int))
    arms_util_include_api('array');
  }
  
  function add($replicaName, $entityGuid) {
    $this->updates[$entityGuid][$replicaName] = 1;
    $this->test->updateEntities(YASS_Engine::singleton()->getReplicaByName($replicaName), array(
      array('guid' => $entityGuid, 'type' => YASS_Test::TESTENTITY, 'data' => sprintf('%s.%d from %s', $entityGuid, $this->updates[$entityGuid][$replicaName], $replicaName)),
    ));
  }
  
  function modify($replicaName, $entityGuid) {
    $this->updates[$entityGuid][$replicaName] = 1+(empty($this->updates[$entityGuid][$replicaName]) ? 0 : $this->updates[$entityGuid][$replicaName]);
    $this->test->updateEntities(YASS_Engine::singleton()->getReplicaByName($replicaName), array(
      array('guid' => $entityGuid, 'type' => YASS_Test::TESTENTITY, 'data' => sprintf('%s.%d from %s', $entityGuid, $this->updates[$entityGuid][$replicaName], $replicaName)),
    ));
  }
  
  function del($replicaName, $entityGuid) {
    $this->updates[$entityGuid][$replicaName] = 1;
    $this->test->updateEntities(YASS_Engine::singleton()->getReplicaByName($replicaName), array(
      array('guid' => $entityGuid, 'type' => YASS_Test::TESTENTITY, 'data' => '', 'exists' => FALSE),
    ));
  }
}
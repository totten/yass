<?php

require_once 'YASS/Test/Evaluator.php';

/**
 * A helper which evaluates statements in the testing DSL. This variant creates
 * array-based entities which can help testing access control (authorization).
 * The entities include these keys:
 *
 *  - changeBy: string, replica name
 *  - changeNum: int, replica-local count of revisions to the given entity. "1" for initial create; "2" for the first modify; "3" for the next modify
 *  - #acl: array, replica names
 *
 * The #testAclByName is not specificially supported by the data-store. However, the
 * master uses the ACLByName filter which translate #testAclByName to the normal #acl
 * format. This is intended to be the simplest way to imitate the standard dataflows
 * (in which customizable fields are used to generate ACLs).
 */
class YASS_Test_AuthEntityEvaluator extends YASS_Test_Evaluator {

  function __construct(YASS_Test $test) {
    parent::__construct($test);
    $this->updates = array(); // array(entityGuid => array(replicaName => int))
    arms_util_include_api('array');
    require_once 'YASS/Filter/ACLByName.php';
  }
  
  /**
   * Overload the initialization for "master", adding access-control options
   */
  function init($replicaName, $opt = NULL) {
    switch ($replicaName) {
      case 'master':
        $replicaSpec = array(
          'name' => $replicaName,
          'datastore' => 'GenericSQL',
          'syncstore' => 'GenericSQL',
          'access_control' => TRUE,
        );
        $replica = $this->test->createReplica($replicaSpec);
        $replica->addFilter(new YASS_Filter_ACLByName(array(
          'namesField' => '#testAclByName',
          'idsField' => '#acl',
          'entityTypes' => array(YASS_Test::TESTENTITY),
          'weight' => 10,
        )));
        return $replica;
      default:
        return parent::init($replicaName, $opt);
    }
  }
  
  function add($replicaName, $entityGuid) {
    $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  
    $this->updates[$entityGuid][$replicaName] = 1;
    $data = array(
      'changeBy' => $replicaName,
      'changeNum' => $this->updates[$entityGuid][$replicaName],
      '#testAclByName' => array($replica->name),
    );
    
    $this->test->updateEntities($replica, array(
      array('guid' => $entityGuid, 'type' => YASS_Test::TESTENTITY, 'data' => $data),
    ));
  }
  
  function modify($replicaName, $entityGuid) {
    $this->has($replicaName, $entityGuid);
    
    $entities = YASS_Engine::singleton()->getReplicaByName($replicaName)->data->getEntities(array($entityGuid));
    $this->updates[$entityGuid][$replicaName] = 1+(empty($this->updates[$entityGuid][$replicaName]) ? 0 : $this->updates[$entityGuid][$replicaName]);
    
    $data = $entities[$entityGuid]->data;
    $data['changeBy'] = $replicaName;
    $data['changeNum'] = $this->updates[$entityGuid][$replicaName];
    
    $this->test->updateEntities(YASS_Engine::singleton()->getReplicaByName($replicaName), array(
      array('guid' => $entityGuid, 'type' => YASS_Test::TESTENTITY, 'data' => $data),
    ));
  }
    
  /**
   * Modify an entity s.t. the entity should be visible to the target replica
   */
  function auth($replicaName, $entityGuid, $targetReplicaName) {
    $entities = YASS_Engine::singleton()->getReplicaByName($replicaName)->data->getEntities(array($entityGuid));
    $this->updates[$entityGuid][$replicaName] = 1+(empty($this->updates[$entityGuid][$replicaName]) ? 0 : $this->updates[$entityGuid][$replicaName]);
    
    $data = $entities[$entityGuid]->data;
    $data['changeBy'] = $replicaName;
    $data['changeNum'] = $this->updates[$entityGuid][$replicaName];
    $data['#testAclByName'] = (!is_array($data['#testAclByName']))
      ? array($targetReplicaName)
      : array_merge($data['#testAclByName'], array($targetReplicaName));
    
    $this->test->updateEntities(YASS_Engine::singleton()->getReplicaByName($replicaName), array(
      array('guid' => $entityGuid, 'type' => YASS_Test::TESTENTITY, 'data' => $data),
    ));
  }

  /**
   * Modify an entity s.t. the entity should be invisible to the target replica
   */
  function deauth($replicaName, $entityGuid, $targetReplicaName) {
    $entities = YASS_Engine::singleton()->getReplicaByName($replicaName)->data->getEntities(array($entityGuid));
    $this->updates[$entityGuid][$replicaName] = 1+(empty($this->updates[$entityGuid][$replicaName]) ? 0 : $this->updates[$entityGuid][$replicaName]);
    
    $data = $entities[$entityGuid]->data;
    $data['changeBy'] = $replicaName;
    $data['changeNum'] = $this->updates[$entityGuid][$replicaName];
    $data['#testAclByName'] = (!is_array($data['#testAclByName']))
      ? array()
      : array_diff($data['#testAclByName'], array($targetReplicaName));
    
    $this->test->updateEntities(YASS_Engine::singleton()->getReplicaByName($replicaName), array(
      array('guid' => $entityGuid, 'type' => YASS_Test::TESTENTITY, 'data' => $data),
    ));
  }
  
}

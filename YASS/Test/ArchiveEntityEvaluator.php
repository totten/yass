<?php

require_once 'YASS/Test/AuthEntityEvaluator.php';

/**
 *
 */
class YASS_Test_ArchiveEntityEvaluator extends YASS_Test_AuthEntityEvaluator {
    public function __construct(YASS_Test $test) {
      parent::__construct($test);
      $this->stashPoints = array();
      require_once 'YASS/Filter/Archive.php';
    }

    /**
     * Overload the initialization for "master", adding access-control options
     */
    function init($replicaName, $opt = NULL) {
        $replica = parent::init($replicaName, $opt);
        switch ($replicaName) {
            case 'master':
                $replica->addFilter(new YASS_Filter_Archive(array(
                    'weight' => -999,
                )));
                break;
            default:
                break;
        }
        return $replica;
    }
    
    function stash($replicaName, $entityGuid, $stashName) {
        $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
        
        $entities = $replica->data->getEntities(array($entityGuid));
        $this->test->assertFalse(empty($entities[$entityGuid]->data));
        $syncStates = $replica->sync->getSyncStates(array($entityGuid));
        $this->test->assertFalse(empty($syncStates[$entityGuid]->modified));
        
        $this->stashPoints[$stashName] = array(
            'version' => $syncStates[$entityGuid]->modified,
            'data' => $entities[$entityGuid]->data,
        );
    }
    
    function checkStash($replicaName, $entityGuid, $stashName) {
        $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
        $entities = $replica->data->getEntities(array($entityGuid));
        $this->test->assertFalse(empty($entities[$entityGuid]->data));
        $this->test->assertFalse(empty($this->stashPoints[$stashName]['data']));
        $this->test->assertEqual($this->stashPoints[$stashName]['data'], $entities[$entityGuid]->data, sprintf('Entity "%s" on replica "%s" should match stashed data "%s"', $entityGuid, $replicaName, $stashName));
    }
    
    function checkNotStash($replicaName, $entityGuid, $stashName) {
        $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
        $entities = $replica->data->getEntities(array($entityGuid));
        $this->test->assertFalse(empty($this->stashPoints[$stashName]['data']));
        $this->test->assertNotEqual($this->stashPoints[$stashName]['data'], $entities[$entityGuid]->data, sprintf('Entity "%s" on replica "%s" should NOT match stashed data "%s"', $entityGuid, $replicaName, $stashName));
    }
    
    function restore($replicaName, $entityGuid, $stashName) {
        $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
        YASS_Engine::singleton()->restore($replica, array(
          $entityGuid => $this->stashPoints[$stashName]['version'],
        ));
    }
}

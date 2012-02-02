<?php

/*
 +--------------------------------------------------------------------+
 | YASS                                                               |
 +--------------------------------------------------------------------+
 | Copyright ARMS Software LLC (c) 2011-2012                          |
 +--------------------------------------------------------------------+
 | This file is a part of YASS.                                       |
 |                                                                    |
 | YASS is free software; you can copy, modify, and distribute it     |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007.                                       |
 |                                                                    |
 | YASS is distributed in the hope that it will be useful, but        |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | Additional permissions may be granted. See LICENSE.txt for         |
 | details.                                                           |
 +--------------------------------------------------------------------+
*/

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
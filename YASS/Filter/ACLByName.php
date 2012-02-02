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

require_once 'YASS/Filter.php';

/**
 * A filter for the master replica which reads a list of replica-names
 * and generates #acl fields.
 */
class YASS_Filter_ACLByName extends YASS_Filter {
    
    /**
     *
     * Note: arms_util_array_combine_properties(YASS_Engine::singleton()->getReplicas(), 'name', 'id')
     *
     * @param $spec array; keys:
     *  - entityTypes: array(entityType)
     *  - namesField: string, name of the global field which stores the list of authorized replica names
     *  - idsField: string, name of the local field which stores the list of authorized replica names
     */
    function __construct($spec) {
        if (!isset($spec['delim'])) {
            $spec['delim'] = '/';
        }
        parent::__construct($spec);
        arms_util_include_api('array');
        $this->entityTypes = drupal_map_assoc($spec['entityTypes']);
        $this->namesFieldPath = explode($spec['delim'], $spec['namesField']);
        $this->idsFieldPath =  explode($spec['delim'], $spec['idsField']);
    }
    
    function toLocal(&$entities, YASS_Replica $replica) {
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if (isset($this->entityTypes[$entity->entityType])) {
                arms_util_array_set($entity->data, $this->idsFieldPath, 
                    $this->createAcl(
                        arms_util_array_resolve($entity->data, $this->namesFieldPath)
                    )
                );
            }
        }
    }
    
    function toGlobal(&$entities, YASS_Replica $replica) {
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if (isset($this->entityTypes[$entity->entityType])) {
                arms_util_array_unset($entity->data, $this->idsFieldPath);
            }
        }
    }
    
    /**
     * Determine the ACL for an entity given its gender and sport
     *
     * @return array(replicaId)
     */
    function createAcl($replicaNames) {
        if (empty($replicaNames)) {
            return array();
        }
        
        $replicas = YASS_Engine::singleton()->getActiveReplicas();
        $allReplicaIdsByName = arms_util_array_combine_properties($replicas, 'name', 'id');
        
        $result = array();
        foreach ($replicaNames as $replicaName) {
            if ($allReplicaIdsByName[$replicaName]) {
                $result[] = $allReplicaIdsByName[$replicaName];
            }
            // FIXME: else { warning unknown replica name }
        }
        return $result;
    }
}

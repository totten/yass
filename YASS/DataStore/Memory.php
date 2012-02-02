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

require_once 'YASS/DataStore.php';
require_once 'YASS/Replica.php';

class YASS_DataStore_Memory extends YASS_DataStore {

    /**
     * 
     */
    public function __construct(YASS_Replica $replica) {
        arms_util_include_api('array');
        $this->replica = $replica;
        $this->entities = array();
    }
    
    /**
     * @var array YASS_Entity
     */
    var $entities;
    
    /**
     * Get the content of an entity
     *
     * @return array(entityGuid => YASS_Entity)
     */
    function _getEntities($entityGuids) {
        return arms_util_array_cloneAll(
            arms_util_array_keyslice($this->entities, $entityGuids)
        );
    }

    /**
     * Save an entity
     *
     * @param $entities array(YASS_Entity)
     */
    function _putEntities($entities) {
        foreach ($entities as $entity) {
            if ($entity->exists) {
                $this->entities[$entity->entityGuid] = $entity;
            } else {
                unset($this->entities[$entity->entityGuid]);
            }
        }
    }
    
    /**
     * Get a list of all entities
     *
     * This is an optional interface to facilitate testing/debugging
     *
     * @return array(entityGuid => YASS_Entity)
     */
    function getAllEntitiesDebug() {
        return $this->entities;
    }
}


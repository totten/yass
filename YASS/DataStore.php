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

require_once 'YASS/ReplicaListener.php';
require_once 'YASS/Entity.php';
require_once 'YASS/Filter/Chain.php';
require_once 'YASS/IDataStore.php';

abstract class YASS_DataStore extends YASS_ReplicaListener implements YASS_IDataStore {
    /**
     * @var YASS_Replica
     */
    var $replica;
     
    /**
     * Get the content of several entities
     *
     * @param $entityGuids array(entityGuid)
     * @return array(entityGuid => YASS_Entity)
     */
    function getEntities($entityGuids) {
        if (empty($entityGuids)) return array();
        $entities = $this->_getEntities($entityGuids);
        foreach ($entityGuids as $entityGuid) {
            if (!isset($entities[$entityGuid])) {
                // FIXME: entityType ?= FALSE
                $entities[$entityGuid] = new YASS_Entity($entityGuid, FALSE, FALSE, FALSE);
            }
        }
        $this->replica->filters->toGlobal($entities, $this->replica);
        return $entities;
    }

    /**
     * Get the content of several entities
     *
     * @param $entityGuids array(entityGuid)
     * @return array(entityGuid => YASS_Entity)
     */
    abstract function _getEntities($entityGuids);

    /**
     * Save an entity
     *
     * @param $entities array(YASS_Entity)
     */
    function putEntities($entities) {
        if (empty($entities)) return;
        $this->replica->filters->toLocal($entities, $this->replica);
        return $this->_putEntities($entities);
    }
    
    /**
     * Save an entity
     *
     * @param $entities array(YASS_Entity)
     */
    abstract function _putEntities($entities);
    
    /**
     * Get a list of all entities
     *
     * This is an optional interface to facilitate testing/debugging
     *
     * @return array(entityGuid => YASS_Entity)
     */
    function getAllEntitiesDebug() {
        return FALSE;
    }
    
}

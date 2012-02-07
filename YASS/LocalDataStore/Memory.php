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

require_once 'YASS/ILocalDataStore.php';
require_once 'YASS/SyncStore/CiviCRM.php';
require_once 'YASS/Replica.php';

class YASS_LocalDataStore_Memory implements YASS_ILocalDataStore {

    /**
     * @var array(entityType => lid)
     */
    var $maxIds;
    
    /**
     * @var array(entityType => array(lid => data))
     */
    var $entities;
    
    /**
     * 
     */
    public function __construct() {
        arms_util_include_api('array');
        $this->entities = array();
        $this->maxIds = array();
    }

    /**
     *
     * @return array(entityType)
     */
    function getEntityTypes() {
        return array(
            'contact', 'activity', 'testentity', 'irrelevant',
            'civicrm_contact', 'civicrm_address', 'civicrm_phone', 'civicrm_email',
            'civicrm_activity','civicrm_activity_assignment','civicrm_activity_target',
            'yass_conflict', 'yass_mergelog',
        );
    }
    
    /**
     * Detremine the order in which entities should be written to DB.
     *
     * Low-weight items are inserted before high-weight items.
     * High-weight items are deleted before low-weight items.
     *
     * @return array(entityType => weight)
     */

    function getEntityWeights() {
        // FIXME Establish ordering without activating CiviCRM
        civicrm_initialize();
        require_once 'CRM/Core/TableHierarchy.php';
        return CRM_Core_TableHierarchy::info();
    }

    /**
     * Read a batch of entities
     *
     * @var $lids array(entityGuid => lid)
     * @return array(entityGuid => YASS_Entity)
     */
    function getEntities($type, $lids) {
        $result = array(); // array(entityGuid => YASS_Entity)
        foreach ($lids as $entityGuid => $lid) {
            if ($this->entities[$type][$lid]) {
                $result[$entityGuid] = new YASS_Entity($entityGuid, $type, $this->entities[$type][$lid]);
            }
        }
        return $result;
    }
    
    /**
     * Add a new entity and generate a new local-id
     *
     * @return local id
     * @throws Exception
     */
    function insert($type, $data) {
        if (!isset($this->maxIds[$type])) {
            $this->maxIds[$type] = 0;
        }
        $lid = ++ $this->maxIds[$type];
        $this->entities[$type][$lid] = $data;
        return $lid;
    }
    
    /**
     * Insert an entity using a specific local-id. If it already exists, then update it.
     *
     * @return void
     * @throws Exception
     */
    function insertUpdate($type, $lid, $data) {
        $this->entities[$type][$lid] = $data;
    }
    
    /**
     * Delete an entity
     */
    function delete($type, $lid) {
        unset($this->entities[$type][$lid]);
    }

    /**
     * Get a list of all entities
     *
     * This is an optional interface to facilitate testing/debugging
     *
     * @return array(entityGuid => YASS_Entity)
     */
    function getAllEntitiesDebug($type, YASS_IGuidMapper $mapper) {
        $result = array();
        if (!is_array($this->entities[$type])) return $result;
        
        foreach ($this->entities[$type] as $lid => $entity) {
            $entityGuid = $mapper->toGlobal($type, $lid);
            $result[$entityGuid] = new YASS_Entity($entityGuid, $type, $this->entities[$type][$lid]);
        }
        return $result;
    }

    function onValidateGuids(YASS_Replica $replica) {
        // create GUIDs for any unmapped entities
        foreach ($this->entities as $type => $entities) {
            foreach ($entities as $lid => $entity) {
                $entityGuid = $replica->mapper->toGlobal($type, $lid);
                // printf("onPreSync: %s [%s:%s]=>[%s]\n", $replica->name, $type, $lid, $entityGuid);
                if (empty($entityGuid)) {
                    $entityGuid = YASS_Engine::singleton()->createGuid();
                    $replica->mapper->addMappings(array(
                        $type => array($lid => $entityGuid)
                    ));
                    $replica->sync->onUpdateEntity($entityGuid);
                    // printf("onPreSync: %s [%s:%s]=>[%s] (generated)\n", $replica->name, $type, $lid, $entityGuid);
                }
            }
        }
    }
    
}

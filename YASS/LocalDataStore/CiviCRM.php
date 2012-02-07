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
require_once 'YASS/Schema/CiviCRM.php';

class YASS_LocalDataStore_CiviCRM implements YASS_ILocalDataStore {

    /**
     * @var array(entityType => ARMS_Select)
     */
    var $queries;
    
    /**
     * @var YASS_Schema_CiviCRM
     */
    var $schema;

    /**
     * 
     */
    public function __construct(YASS_Replica $replica, YASS_Schema_CiviCRM $schema) {
        arms_util_include_api('array');
        arms_util_include_api('query', 'thinapi');
        $this->replica = $replica;
        $this->schema = $schema;
    }
    
    /**
     *
     * @return array(entityType)
     */
    function getEntityTypes() {
        return $this->schema->getEntityTypes();
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
        $guids = array_flip($lids); // array(lid => entityGuid)

        $idColumn = 'id';
        $select = $this->buildFullEntityQuery($type);
        $select->addWhere(arms_util_query_in($type.'.'.$idColumn, $lids));
        $q = db_query($select->toSQL());
        while ($data = db_fetch_array($q)) {
           $entityGuid = $guids[$data[$idColumn]];
           unset($data[$idColumn]);
           $result[$entityGuid] = new YASS_Entity($entityGuid, $type, $data);
        }
        
        return $result;
    }
    
    /**
     * Get a query which fetches the the full details of an entity
     *
     * @param $type entityType
     * @return ARMS_Util_Select
     */
    protected function buildFullEntityQuery($type) {
        if (! isset($this->queries[$type])) {
            $this->queries[$type] = arms_util_thinapi(array(
                'entity' => $type,
                'action' => 'select',
            ));
        }
        return clone $this->queries[$type]['select'];
    }
    
    /**
     * Add a new entity and generate a new local-id
     *
     * @return local id
     * @throws Exception
     */
    function insert($type, $data) {
        db_query('SET @yass_disableTrigger = 1');
        $result = arms_util_thinapi(array(
            'entity' => $type,
            'action' => 'insert',
            'data' => $data,
        ));
        db_query('SET @yass_disableTrigger = NULL'); // FIXME: try {...} finally {...}
        return $result['data']['id'];
    }
    
    /**
     * Insert an entity using a specific local-id. If it already exists, then update it.
     *
     * @return void
     * @throws Exception
     */
    function insertUpdate($type, $lid, $data) {
        db_query('SET @yass_disableTrigger = 1');
        $result = arms_util_thinapi(array(
            'entity' => $type,
            'action' => 'insert-update',
            'data' => $data + array('id' => $lid),
        ));
        db_query('SET @yass_disableTrigger = NULL'); // FIXME: try {...} finally {...}
    }
    
    /**
     * Delete an entity
     */
    function delete($type, $lid) {
        db_query('SET @yass_disableTrigger = 1');
        $result = arms_util_thinapi(array(
            'entity' => $type,
            'action' => 'delete',
            'data' => array('id' => $lid),
        ));
        db_query('SET @yass_disableTrigger = NULL'); // FIXME: try {...} finally {...}
    }

    /**
     * Get a list of all entities
     *
     * This is an optional interface to facilitate testing/debugging
     *
     * @return array(entityGuid => YASS_Entity)
     */
    function getAllEntitiesDebug($type, YASS_IGuidMapper $mapper) {
        $result = array(); // array(entityGuid => YASS_Entity)
        $idColumn = 'id';
        $select = $this->buildFullEntityQuery($type);
        $q = db_query($select->toSQL());
        while ($data = db_fetch_array($q)) {
            $entityGuid = $mapper->toGlobal($type, $data[$idColumn]);
            if (!$entityGuid) {
                printf("Unmapped entity (%s:%s)\n", $type, $data[$idColumn]); // FIXME error handling
            } else {
                $result[$entityGuid] = new YASS_Entity($entityGuid, $type, $data);
            }
        }
        return $result;
    }

}

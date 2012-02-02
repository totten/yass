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
require_once 'YASS/Replica.php';

/**
 * FIXME Read metadata from Schema API. This is currently hard-coded to work with yass_conflict.
 */
class YASS_LocalDataStore_Drupal implements YASS_ILocalDataStore {

    /**
     * @var array(entityType => ARMS_Select)
     */
    var $queries;

    /**
     * 
     */
    public function __construct() {
        arms_util_include_api('array');
        arms_util_include_api('query');
    }
    
    /**
     *
     * @return array(entityType)
     */
    function getEntityTypes() {
        return array_keys($this->getEntityWeights());
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
        return array(
            'yass_conflict' => '90',
        );
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

        $idColumn = 'id'; // FIXME schema
        $select = $this->buildFullEntityQuery($type);
        $select->addWhere(arms_util_query_in($type.'.'.$idColumn, $lids));
        $q = db_query($select->toSQL());
        while ($data = db_fetch_array($q)) {
           $entityGuid = $guids[$data[$idColumn]];
           unset($data[$idColumn]);
           
           $data['win_entity'] = unserialize($data['win_entity']);
           $data['lose_entity'] = unserialize($data['lose_entity']);
           
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
            $select = arms_util_query($type);
            $select->addSelect("{$type}.*");
            $this->queries[$type] = $select;
        }
        return clone $this->queries[$type];
    }
    
    /**
     * Add a new entity and generate a new local-id
     *
     * @return local id
     * @throws Exception
     */
    function insert($type, $data) {
        db_query('SET @yass_disableTrigger = 1');
        drupal_write_record($type, $data);
        db_query('SET @yass_disableTrigger = NULL'); // FIXME: try {...} finally {...}
        return $data['id']; // FIXME schema
    }
    
    /**
     * Insert an entity using a specific local-id. If it already exists, then update it.
     *
     * @return void
     * @throws Exception
     */
    function insertUpdate($type, $lid, $data) {
        assert('$type == "yass_conflict"');
        db_query('SET @yass_disableTrigger = 1');
        $q = db_query('SELECT id FROM {yass_conflict} WHERE id = %d', $lid); // FIXME schema
        if (db_result($q)) {
            drupal_write_record($type, $data, 'id');
        } else {
            drupal_write_record($type, $data);
        }
        db_query('SET @yass_disableTrigger = NULL'); // FIXME: try {...} finally {...}
    }
    
    /**
     * Delete an entity
     */
    function delete($type, $lid) {
        assert('$type == "yass_conflict"');
        db_query('SET @yass_disableTrigger = 1');
        db_query('DELETE FROM {yass_conflict} WHERE id = %d', $lid); // FIXME schema
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
        $idColumn = 'id'; // FIXME schema
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

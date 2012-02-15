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
 * Store localized versions of YASS entities (eg yass_conflict, yass_mergelog)
 */
class YASS_LocalDataStore_GenericSQL implements YASS_ILocalDataStore {

    /**
     * @var array(entityType => ARMS_Select)
     */
    var $queries;

    /**
     * 
     */
    public function __construct(YASS_Replica $replica, $entityWeights = FALSE) {
        arms_util_include_api('array');
        arms_util_include_api('query');
        $this->replica = $replica;
        
        if (! $entityWeights) {
            $types = array(
                'contact', 'activity', 'testentity', 'irrelevant',
                'civicrm_contact', 'civicrm_address', 'civicrm_phone', 'civicrm_email', 'civicrm_website',
                'civicrm_activity','civicrm_activity_assignment','civicrm_activity_target',
                'yass_conflict', 'yass_mergelog',
            );
            $entityWeights = array();
            foreach ($types as $type) {
                $entityWeights[$type] = '10';
            }
        }
        
        $this->entityWeights = $entityWeights;
        
        
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
        return $this->entityWeights;
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

        $select = $this->buildFullEntityQuery($type);
        $select->addWhere(arms_util_query_in('id', $lids));
        $q = db_query($select->toSQL());
        while ($data = db_fetch_array($q)) {
           $entityGuid = $guids[$data['id']];
           $result[$entityGuid] = new YASS_Entity($entityGuid, $type, unserialize($data['data']));
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
            $select = arms_util_query('yass_datastore_local');
            $select->addSelect("*");
            $select->addWheref('replica_id = %d', $this->replica->id);
            $select->addWheref('entity_type = "%s"', $type);
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
        $row = array(
          'replica_id' => $this->replica->id,
          'entity_type' => $type,
          'data' => $data,
        );
        
        db_query('SET @yass_disableTrigger = 1');
        drupal_write_record('yass_datastore_local', $row);
        db_query('SET @yass_disableTrigger = NULL'); // FIXME: try {...} finally {...}
        return $row['id'];
    }
    
    /**
     * Insert an entity using a specific local-id. If it already exists, then update it.
     *
     * @return void
     * @throws Exception
     */
    function insertUpdate($type, $lid, $data) {
        $row = array(
          'replica_id' => $this->replica->id,
          'entity_type' => $type,
          'id' => $lid,
          'data' => $data,
        );
        
        db_query('SET @yass_disableTrigger = 1');
        $q = db_query('SELECT id FROM {yass_datastore_local} WHERE replica_id = %d AND entity_type = "%s" AND id = %d', $this->replica->id, $type, $lid);
        if (db_result($q)) {
            drupal_write_record('yass_datastore_local', $row, 'id');
        } else {
            drupal_write_record('yass_datastore_local', $row);
        }
        db_query('SET @yass_disableTrigger = NULL'); // FIXME: try {...} finally {...}
    }
    
    /**
     * Delete an entity
     */
    function delete($type, $lid) {
        db_query('SET @yass_disableTrigger = 1');
        db_query('DELETE FROM {yass_datastore_local} WHERE replica_id = %d AND entity_type = "%s" AND id = %d', $this->replica->id, $type, $lid);
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
        $select = $this->buildFullEntityQuery($type);
        $q = db_query($select->toSQL());
        while ($row = db_fetch_array($q)) {
            $entityGuid = $mapper->toGlobal($type, $row['id']);
            if (!$entityGuid) {
                printf("Unmapped entity (%s:%s)\n", $type, $row['id']); // FIXME error handling
            } else {
                $result[$entityGuid] = new YASS_Entity($entityGuid, $type, unserialize($row['data']));
            }
        }
        return $result;
    }
}

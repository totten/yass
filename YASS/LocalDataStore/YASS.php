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
class YASS_LocalDataStore_YASS implements YASS_ILocalDataStore {

    /**
     * @var array(entityType => ARMS_Select)
     */
    var $queries;

    /**
     * 
     */
    public function __construct(YASS_Replica $replica) {
        arms_util_include_api('array');
        arms_util_include_api('query');
        $this->replica = $replica;
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
            'yass_mergelog' => '95',
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

        $idColumn = 'id'; // FIXME PK from schema
        $select = $this->buildFullEntityQuery($type);
        $select->addWhere(arms_util_query_in($type.'.'.$idColumn, $lids));
        $q = db_query($select->toSQL());
        while ($data = db_fetch_array($q)) {
           $entityGuid = $guids[$data[$idColumn]];
           unset($data[$idColumn]);
           switch($type) {
               case 'yass_conflict':
                   $data['win_entity'] = unserialize($data['win_entity']);
                   $data['lose_entity'] = unserialize($data['lose_entity']);
                   break;
               default:
           }
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
        switch ($type) {
            case 'yass_mergelog':
              $this->onInsertMergeLog($data);
              break;
            default:
        }
        return $data['id']; // FIXME PK from schema
    }
    
    /**
     * Insert an entity using a specific local-id. If it already exists, then update it.
     *
     * @return void
     * @throws Exception
     */
    function insertUpdate($type, $lid, $data) {
        $idColumn = 'id'; // FIXME PK from schema
        db_query('SET @yass_disableTrigger = 1');
        $q = db_query('SELECT id FROM {'.$type.'} WHERE '.$idColumn.' = %d', $lid);
        if (db_result($q)) {
            drupal_write_record($type, $data, $idColumn);
        } else {
            drupal_write_record($type, $data);
        }
        db_query('SET @yass_disableTrigger = NULL'); // FIXME: try {...} finally {...}
    }
    
    /**
     * Delete an entity
     */
    function delete($type, $lid) {
        $idColumn = 'id'; // FIXME PK from schema
        db_query('SET @yass_disableTrigger = 1');
        db_query('DELETE FROM {'.$type.'} WHERE '.$idColumn.' = %d', $lid);
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
        $idColumn = 'id'; // FIXME PK from schema
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
    
    /**
     *
     * @todo better placement
     * @param array {yass_mergelog}
     */
    protected function onInsertMergeLog($mergelog) {
        if ($this->replica->mergeLogs) {
            $this->replica->mergeLogs->flush();
        }
        if ($mergelog['entity_type'] != 'civicrm_contact') {
            throw new Exception(sprintf('Unsupported merge type [%s]', $mergelog['entity_type']));
        }
        $this->mergeFields($mergelog['kept_id'], $mergelog['destroyed_id']);
        $this->mergeRelations($mergelog['kept_id'], $mergelog['destroyed_id']);
        YASS_Context::get('addendum')->setSyncRequired(TRUE); 
        // FIXME: theory: mergeRelations updates syncstate of related entities but that gets trampled by transfer() logic; need to tick all of them
    }
    
    /**
     * Fill in any blank fields from $keeperId with values from $destroyedId
     *
     * @todo better placement
     * @param $keeperId int, contact id
     * @param $destroyedId int, contact id
     */
    protected function mergeFields($keeperId, $destroyedId) {
        $keeperQuery = arms_util_thinapi(array('entity' => 'civicrm_contact', 'action' => 'select'));
        $keeperQuery['select']->addWheref("civicrm_contact.id = %d", $keeperId);
        $keeper = db_fetch_array(db_query($keeperQuery['select']->toSQL()));
        if (!$keeper) return;
        
        $destroyedQuery = arms_util_thinapi(array('entity' => 'civicrm_contact', 'action' => 'select'));
        $destroyedQuery['select']->addWheref("civicrm_contact.id = %d", $destroyedId);
        $destroyed = db_fetch_array(db_query($destroyedQuery['select']->toSQL()));
        if (!$destroyed) return;
        
        foreach ($destroyed as $key => $value) {
            if ($keeper[$key] === NULL || $keeper[$key] === '' || $keeper[$key] === array()) {
                $keeper[$key] = $value;
            }
        }
        
        arms_util_thinapi(array(
            'entity' => 'civicrm_contact',
            'action' => 'update',
            'data' => $keeper,
        ));
    }
    
    protected function mergeRelations($keeperId, $destroyedId) {
        civicrm_initialize();
        require_once 'CRM/Dedupe/Merger.php';
        $allTables = array_keys(CRM_Dedupe_Merger::cidRefs()) + array_keys(CRM_Dedupe_Merger::eidRefs());
        CRM_Dedupe_Merger::moveContactBelongings($keeperId, $destroyedId, $allTables);   
    }
}

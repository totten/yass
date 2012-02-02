<?php

require_once 'YASS/ILocalDataStore.php';
require_once 'YASS/SyncStore/CiviCRM.php';
require_once 'YASS/Replica.php';

class YASS_LocalDataStore_CiviCRM implements YASS_ILocalDataStore {

    /**
     * @var array(entityType => ARMS_Select)
     */
    var $queries;

    /**
     * 
     */
    public function __construct(YASS_Replica $replica /*deprecated*/) {
        arms_util_include_api('array');
        arms_util_include_api('query');
        $this->replica = $replica;
    }
    
    /**
     *
     * @return array(entityType)
     */
    function getEntityTypes() {
        return $this->replica->schema->getEntityTypes();
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
            $select = arms_util_query($type);
            $select->addSelect("{$type}.*");
            $fields = $this->replica->schema->getCustomFields($type);
            foreach ($fields as $field) {
                $select->addCustomField("{$type}.id", $field, 'custom_' . $field['id']);
            }
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
<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore/ARMS.php';
require_once 'YASS/Replica.php';

class YASS_DataStore_ARMS extends YASS_DataStore {

	/**
	 * 
	 */
	public function __construct(YASS_Replica $replica) {
		arms_util_include_api('array');
		arms_util_include_api('query');
		$this->replica = $replica;
	}
	
	/**
	 * Get the content of an entity
	 *
	 * @return array(entityGuid => YASS_Entity)
	 */
	function getEntities($entityGuids) {
		$this->replica->mapper->loadGlobalIds($entityGuids);
		
		$lidsByType = array(); // array(type => array(lid))
		foreach ($entityGuids as $entityGuid) {
		  list ($type,$lid) = $this->replica->mapper->toLocal($entityGuid);
		  $lidsByType[$type][] = $lid;
		}
		
		$result = array(); // array(entityGuid => YASS_Entity)
		foreach ($lidsByType as $type => $lids) {
			$idColumn = 'id';
			$select = $this->buildFullEntityQuery($type);
			$select->addWhere(arms_util_query_in($type.'.'.$idColumn, $lids));
			$q = db_query($select->toSQL());
			while ($data = db_fetch_array($q)) {
				$entityGuid = $this->replica->mapper->toGlobal($type, $data[$idColumn]);
				unset($data[$idColumn]);
				$result[$entityGuid] = new YASS_Entity($entityGuid, $type, $data);
			}
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
	 * Save an entity
	 *
	 * @param $entities array(YASS_Entity)
	 */
	function putEntities($entities) {
		$this->replica->mapper->loadGlobalIds(array_keys($entities));
		foreach ($entities as $entity) {
			if (!in_array($entity->entityType, $this->replica->schema->getEntityTypes())) {
				continue;
			}
			
			// FIXME: if it happens that guidmapper retains an
			// old id from a deleted entity and we try to
			// restore it, the nativeUpdate may not be
			// sufficient
			
			$dataByGroup = arms_util_array_partition_func($entity->data, array($this, 'getFieldGroup'));
			
			list ($type, $lid) = $this->replica->mapper->toLocal($entity->entityGuid);
			if (! ($type && $lid)) {
				$lid = $this->nativeAdd($entity->entityType, $dataByGroup['core']);
				$this->replica->mapper->addMappings(array(
					$entity->entityType => array($lid => $entity->entityGuid)
				));
			} else {
				$this->nativeUpdate($type, $lid, $dataByGroup['core']);
			}
			$this->nativeCustomSave($type, $lid, $dataByGroup);
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
		$result = array(); // array(entityGuid => YASS_Entity)
		foreach ($this->replica->schema->getEntityTypes() as $type) {
			$idColumn = 'id';
			$select = $this->buildFullEntityQuery($type);
			$q = db_query($select->toSQL());
			while ($data = db_fetch_array($q)) {
				$entityGuid = $this->replica->mapper->toGlobal($type, $data[$idColumn]);
				if (!$entityGuid) {
					printf("Unmapped entity (%s:%s)\n", $type, $data[$idColumn]); // FIXME error handling
				} else {
					$result[$entityGuid] = new YASS_Entity($entityGuid, $type, $data);
				}
			}
		}
		return $result;
	}

	/**
	 * Put content directly in the data store, bypassing the synchronization system.
	 * This creates an un-synchronized entity.
	 *
	 * @return int, local id
	 */
	function nativeAdd($type, $data) {
		$idColumn = 'id';
		db_query('SET @yass_disableTrigger = 1');
		db_query(arms_util_insert($type)->addValues($data)->toSQL());
		$lid = db_last_insert_id($type, $idColumn);
		db_query('SET @yass_disableTrigger = NULL');
		return $lid;
	}

	/**
	 * Put content directly in the data store, bypassing the synchronization system.
	 * This creates an un-synchronized entity.
	 *
	 * @param $lid int, local id
	 */
	function nativeUpdate($type, $lid, $data) {
		$idColumn = 'id';
		db_query('SET @yass_disableTrigger = 1');
		db_query(arms_util_update($type)->addWheref("${idColumn}=%d", $lid)->addValues($data)->toSQL());
		db_query('SET @yass_disableTrigger = NULL');
	}
	
	/**
	 * Insert or update any custom-value records
	 *
	 * @param $type string
	 * @param $lid int the ID of the primary entity
	 * @param $dataByGroup array(groupName => array(fieldId => fieldValue))
	 */
	function nativeCustomSave($type, $lid, $dataByGroup) {
		foreach ($dataByGroup as $groupName => $groupValues) {
			if ($groupName == 'core') continue;
			$group = arms_util_group($groupName);
			$columnValues = arms_util_array_rekey_rosetta($group['fields'], '_param', 'column_name', $groupValues);

			$idColumn = 'entity_id';
			$insert = arms_util_insert($group['table_name'], 'update')
				->addValue($idColumn, $lid, 'insert-only')
				->addValues($columnValues, 'insert-update');
			
			db_query('SET @yass_disableTrigger = 1');
			db_query($insert->toSQL());
			db_query('SET @yass_disableTrigger = NULL');
		}
	}
	
	/**
	 * Determine which custom-data group which stores a given field
	 *
	 * @param $key field name, e.g. 'first_name' or 'custom_123'
	 * @param $value field value
	 * @return FALSE, 'core', or group-name
	 */
	function getFieldGroup($key, $value) {
		if (preg_match('/^custom_(\d+)$/', $key, $matches)) {
			$field = arms_util_field_by_id($matches[1]);
			if (is_array($field)) {
				return $field['_group_name'];
			} else {
				return FALSE;
			}
		} else {
			return 'core';
		}
	}
}

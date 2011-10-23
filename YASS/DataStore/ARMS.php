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
			$select = arms_util_query($type);
			$select->addSelect('*');
			$select->addWhere(arms_util_query_in($idColumn, $lids));
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
	 * Save an entity
	 *
	 * @param $entities array(YASS_Entity)
	 */
	function putEntities($entities) {
		$this->replica->mapper->loadGlobalIds(array_keys($entities));
		foreach ($entities as $entity) {
			if (!in_array($entity->entityType, YASS_SyncStore_ARMS::$ENTITIES)) {
				continue;
			}
			
			// FIXME: if it happens that guidmapper retains an
			// old id from a deleted entity and we try to
			// restore it, the nativeUpdate may not be
			// sufficient
			
			list ($type, $lid) = $this->replica->mapper->toLocal($entity->entityGuid);
			if (! ($type && $lid)) {
				$lid = $this->nativeAdd($entity->entityType, $entity->data);
				$this->replica->mapper->addMappings(array(
					$entity->entityType => array($lid => $entity->entityGuid)
				));
			} else {
				$this->nativeUpdate($type, $lid, $entity->data);
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
		$result = array(); // array(entityGuid => YASS_Entity)
		foreach (YASS_SyncStore_ARMS::$ENTITIES as $type) {
			$idColumn = 'id';
			$select = arms_util_query($type);
			$select->addSelect('*');
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
	
	function onBuildFilters(YASS_Replica $replica) {
		require_once 'YASS/Filter/OptionValue.php';
		$result = array();
		$result[] = new YASS_Filter_OptionValue(array(
			'entityType' => 'civicrm_activity',
			'field' => 'activity_type_id',
			'group' => 'activity_type',
			'localFormat' => 'value',
			'globalFormat' => 'name',
		));
		return $result;
	}

}

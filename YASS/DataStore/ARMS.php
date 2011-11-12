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
			
			list ($type, $lid) = $this->replica->mapper->toLocal($entity->entityGuid);
			if (! ($type && $lid)) {
				db_query('SET @yass_disableTrigger = 1');
				$result = arms_util_thinapi(array(
					'entity' => $entity->entityType,
					'action' => 'insert',
					'data' => $entity->data,
				));
				db_query('SET @yass_disableTrigger = NULL');
				$lid = $result['data']['id'];
				$this->replica->mapper->addMappings(array(
					$entity->entityType => array($lid => $entity->entityGuid)
				));
			} else {
				db_query('SET @yass_disableTrigger = 1');
				$result = arms_util_thinapi(array(
					'entity' => $entity->entityType,
					'action' => 'update',
					'data' => $entity->data + array('id' => $lid),
				));
				db_query('SET @yass_disableTrigger = NULL');
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

}

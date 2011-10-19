<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/Replica.php';

class YASS_DataStore_GenericSQL extends YASS_DataStore {

	/**
	 * 
	 */
	public function __construct(YASS_Replica $replica) {
		arms_util_include_api('query');
		$this->replicaId = $replica->id;
	}
	
	/**
	 * Get the content of an entity
	 *
	 * @param $entityGuids array(entityGuid)
	 * @return array(entityGuid => YASS_Entity)
	 */
	function getEntities($entityGuids) {
		$select = arms_util_query('{yass_datastore}');
		$select->addSelects(array('entity_id','entity_type','data'));
		$select->addWheref('replica_id=%d', $this->replicaId);
		$select->addWhere(arms_util_query_in('entity_id', $entityGuids));
		$q = db_query($select->toSQL());
		$result = array();
		while ($row = db_fetch_object($q)) {
			$result[$row->entity_id] = $this->toYassEntity($row);
		}
		return $result;
	}

	/**
	 * Save an entity
	 *
	 * @param $entities array(YASS_Entity)
	 */
	function putEntities($entities) {
		foreach ($entities as $entity) {
			$data = serialize($entity->data);
			db_query('INSERT INTO {yass_datastore} (replica_id,entity_type,entity_id,data)
				VALUES (%d,"%s","%s","%s")
				ON DUPLICATE KEY UPDATE data="%s"',
				$this->replicaId, $entity->entityType, $entity->entityGuid, $data,
				$data);
		}
	}
	
	/**
	 * Get a list of all entities
	 *
	 * This is an optional interface to facilitate testing/debugging
	 *
	 * @return array(entityGuid => YASS_Entity)
	 */
	function getAllEntitiesDebug()
	{
		$q = db_query('SELECT entity_id, entity_type, data FROM {yass_datastore} WHERE replica_id=%d',
			$this->replicaId);
		$entities = array();
		while ($row = db_fetch_object($q)) {
			$entities[ $row->entity_id ] = $this->toYassEntity($row);
		}
		return $entities;
	}
	
	/**
	 * Map a SQL row to an object
	 *
	 * @param $row stdClass{yass_datastore}
	 * @return YASS_Entity
	 */
	protected function toYassEntity($row) {
		return new YASS_Entity($row->entity_id, $row->entity_type, unserialize($row->data));
	}
}


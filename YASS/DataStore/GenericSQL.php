<?php

require_once 'YASS/DataStore.php';

class YASS_DataStore_GenericSQL extends YASS_DataStore {
	const GENERIC_ENTITY = 'g';

	/**
	 * 
	 * @param $replicaSpec array{yass_replicas} Specification for the replica
	 */
	public function __construct($replicaSpec) {
		$this->replicaId = $replicaSpec['id'];
	}
	
	/**
	 * Get the content of an entity
	 *
	 * @return YASS_Entity
	 */
	function getEntity($entityGuid) {
		$q = db_query('SELECT entity_id, data
			FROM {yass_datastore} 
			WHERE replica_id=%d AND entity_type="%s" AND entity_id="%s"',
			$this->replicaId, self::GENERIC_ENTITY, $entityGuid);
		if ($row = db_fetch_object($q)) {
			return $this->toYassEntity($row);
		} else {
			return FALSE;
		}
	}

	/**
	 * Save an entity
	 */
	function putEntity(YASS_Entity $entity) {
		$data = serialize($entity->data);
		db_query('INSERT INTO {yass_datastore} (replica_id,entity_type,entity_id,data)
			VALUES (%d,"%s","%s","%s")
			ON DUPLICATE KEY UPDATE data="%s"',
			$this->replicaId, self::GENERIC_ENTITY, $entity->entityGuid, $data,
			$data);
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
		$q = db_query('SELECT entity_id, data FROM {yass_datastore} WHERE replica_id=%d',
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
		return new YASS_Entity($row->entity_id, unserialize($row->data));
	}
}


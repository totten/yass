<?php

require_once 'YASS/DataStore.php';

class YASS_DataStore_GenericSQL extends YASS_DataStore {

	/**
	 * 
	 * @param $metadata array{yass_replicas} Specification for the replica
	 */
	public function __construct($metadata) {
		$this->replicaId = $metadata['id'];
	}
	
	/**
	 * Get the content of an entity
	 *
	 * @return YASS_Entity
	 */
	function getEntity($entityType, $entityGuid) {
		$q = db_query('SELECT data FROM {yass_datastore} WHERE replica_id=%d AND entity_type="%s" and entity_id="%s"',
			$this->replicaId, $entityType, $entityGuid);
		if ($row = db_fetch_object($q)) {
			return new YASS_Entity($entityType, $entityGuid, unserialize($row->data));
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
			$this->replicaId, $entity->entityType, $entity->entityGuid, $data,
			$data);
	}
}


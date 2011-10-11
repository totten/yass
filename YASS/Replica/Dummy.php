<?php

require_once 'YASS/Replica.php';
require_once 'YASS/DataStore/GenericSQL.php';
require_once 'YASS/SyncStore/Memory.php';

class YASS_Replica_Dummy extends YASS_Replica {
	static $_dummyCounter = 1000000;

	/**
	 * Construct a replica
	 *
	 * @param $metadata array{yass_replicas} Specification for the replica
	 */	
	function __construct($metadata) {
		$metadata = array_merge(array(
			'id' => (self::$_dummyCounter ++),
			'datastore' => 'Memory',
			'syncstore' => 'Memory',
			'is_active' => TRUE,
		), $metadata);
		$this->name = $metadata['name'];
		$this->id = $metadata['id'];
		$this->isActive = $metadata['is_active'];
		$this->data = $this->_createDatastore($metadata);
		$this->sync = $this->_createSyncstore($metadata);
	}
	
	/**
	 * Create/update a batch of entities
	 *
	 * @param $rows array(0 => type, 1 => guid, 2 => data)
	 */
	function set($rows) {
		foreach ($rows as $row) {
			$entity = new YASS_Entity($row[0], $row[1], $row[2]);
			$this->data->putEntity($entity);
			$this->sync->onUpdateEntity($entity->entityType, $entity->entityGuid);
		}
	}
	
	/**
	 * Get the full state of an entity
	 *
	 * @return array(0 => replicaId, 1 => tick, 2 => data)
	 */
	function get($type, $guid) {
		$entity = $this->data->getEntity($type, $guid);
		$syncState = $this->sync->getSyncState($type, $guid);
		return array($syncState->modified->replicaId, $syncState->modified->tick, $entity->data);
	}
}

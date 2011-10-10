<?php

require_once 'YASS/Replica.php';
require_once 'YASS/DataStore/Memory.php';
require_once 'YASS/SyncStore/Memory.php';

class YASS_Replica_Dummy extends YASS_Replica {
	function __construct($replicaId) {
		$this->name = 'dummy:' . $replicaId;
		$this->id = $replicaId;
		$this->isActive = TRUE;
		$this->data = new YASS_DataStore_Memory(array('id' => $replicaId));
		$this->sync = new YASS_SyncStore_Memory(array('id' => $replicaId));
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

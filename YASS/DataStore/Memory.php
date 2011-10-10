<?php

require_once 'YASS/DataStore.php';

class YASS_DataStore_Memory extends YASS_DataStore {

	/**
	 * 
	 * @param $metadata array{yass_replicas} Specification for the replica
	 */
	public function __construct($metadata) {
		$this->replicaId = $metadata['id'];
		$this->entities = array();
	}
	
	/**
	 * @var array YASS_Entity
	 */
	var $entities;
	
	/**
	 * Get the content of an entity
	 *
	 * @return YASS_Entity
	 */
	function getEntity($entityType, $entityGuid) {
		return $this->entities[$entityType][$entityGuid];
	}

	/**
	 * Save an entity
	 */
	function putEntity(YASS_Entity $entity) {
		$this->entities[$entity->entityType][$entity->entityGuid] = $entity;
	}
}


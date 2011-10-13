<?php

require_once 'YASS/DataStore.php';

class YASS_DataStore_Memory extends YASS_DataStore {

	/**
	 * 
	 * @param $replicaSpec array{yass_replicas} Specification for the replica
	 */
	public function __construct($replicaSpec) {
		$this->replicaId = $replicaSpec['id'];
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
	
	/**
	 * Get a list of all entities
	 *
	 * This is an optional interface to facilitate testing/debugging
	 *
	 * @return array(entityType => array(entityGuid => YASS_Entity))
	 */
	function getAllEntitiesDebug() {
		return $this->entities;
	}
}


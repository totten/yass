<?php

require_once 'YASS/Engine.php';
require_once 'YASS/DataStore.php';

/**
 * An an in-memory data store for which entities are stored with localized IDs
 */
class YASS_DataStore_LocalizedMemory extends YASS_DataStore {
	/**
	 * @var array(entityType => lid)
	 */
	var $maxIds;
	
	/**
	 * @var array(entityType => array(lid => YASS_Entity))
	 */
	var $entities;

	/**
	 * 
	 * @param $replicaSpec array{yass_replicas} Specification for the replica
	 */
	public function __construct($replicaSpec) {
		arms_util_include_api('array');
		$this->replicaId = $replicaSpec['id'];
		$this->entities = array();
		$this->maxIds = array();
	}
	
	/**
	 * Get the content of an entity
	 *
	 * @return array(entityGuid => YASS_Entity)
	 */
	function getEntities($entityGuids) {
		$mapper = YASS_Engine::singleton()->getReplicaById($this->replicaId)->mapper;
		$mapper->loadGlobalIds($entityGuids);
		$result = array();
		foreach ($entityGuids as $entityGuid) {
			list ($type, $lid) = $mapper->toLocal($entityGuid);
			if ($this->entities[$type][$lid]) {
				$result[$entityGuid] = $this->entities[$type][$lid];
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
		$mapper = YASS_Engine::singleton()->getReplicaById($this->replicaId)->mapper;
		$mapper->loadGlobalIds(array_keys($entities));
		foreach ($entities as $entity) {
			list ($type, $lid) = $mapper->toLocal($entity->entityGuid);
			if (! ($type && $lid)) {
				$type = $entity->entityType;
				if (!isset($this->maxIds[$entity->entityType])) {
					$this->maxIds[$entity->entityType] = 0;
				}
				$lid = ++ $this->maxIds[$entity->entityType];
				$mapper->addMappings(array(
				  $type => array($lid => $entity->entityGuid)
				));
			}
			$this->entities[$type][$lid] = $entity;
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
		$mapper = YASS_Engine::singleton()->getReplicaById($this->replicaId)->mapper;
		$result = array();
		foreach ($this->entities as $type => $entities) {
			foreach ($entities as $lid => $entity) {
				$entityGuid = $mapper->toGlobal($type, $lid);
				$result[$entityGuid] = $entity;
			}
		}
		return $result;
	}
}


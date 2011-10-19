<?php

require_once 'YASS/Engine.php';
require_once 'YASS/DataStore.php';
require_once 'YASS/Replica.php';

/**
 * An an in-memory data store for which entities are stored with localized IDs
 */
class YASS_DataStore_LocalizedMemory extends YASS_DataStore {
	/**
	 * @var array(entityType => lid)
	 */
	var $maxIds;
	
	/**
	 * @var array(entityType => array(lid => data))
	 */
	var $entities;

	/**
	 * 
	 * @param $replicaSpec array{yass_replicas} Specification for the replica
	 */
	public function __construct(YASS_Replica $replica) {
		arms_util_include_api('array');
		$this->replicaId = $replica->id;
		$this->replica = $replica;
		$this->entities = array();
		$this->maxIds = array();
	}
	
	/**
	 * Get the content of an entity
	 *
	 * @return array(entityGuid => YASS_Entity)
	 */
	function getEntities($entityGuids) {
		$this->replica->mapper->loadGlobalIds($entityGuids);
		$result = array();
		foreach ($entityGuids as $entityGuid) {
			list ($type, $lid) = $this->replica->mapper->toLocal($entityGuid);
			if ($this->entities[$type][$lid]) {
				$result[$entityGuid] = new YASS_Entity($entityGuid, $type, $this->entities[$type][$lid]);
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
			list ($type, $lid) = $this->replica->mapper->toLocal($entity->entityGuid);
			if (! ($type && $lid)) {
				$type = $entity->entityType;
				if (!isset($this->maxIds[$entity->entityType])) {
					$this->maxIds[$entity->entityType] = 0;
				}
				$lid = ++ $this->maxIds[$entity->entityType];
				$this->replica->mapper->addMappings(array(
				  $type => array($lid => $entity->entityGuid)
				));
			}
			$this->entities[$type][$lid] = $entity->data;
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
		$result = array();
		foreach ($this->entities as $type => $entities) {
			foreach ($entities as $lid => $entity) {
				$entityGuid = $this->replica->mapper->toGlobal($type, $lid);
				$result[$entityGuid] = new YASS_Entity($entityGuid, $type, $this->entities[$type][$lid]);
			}
		}
		return $result;
	}
	
}


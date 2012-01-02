<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/Replica.php';

class YASS_DataStore_Memory extends YASS_DataStore {

	/**
	 * 
	 */
	public function __construct(YASS_Replica $replica) {
		arms_util_include_api('array');
		$this->replica = $replica;
		$this->entities = array();
	}
	
	/**
	 * @var array YASS_Entity
	 */
	var $entities;
	
	/**
	 * Get the content of an entity
	 *
	 * @return array(entityGuid => YASS_Entity)
	 */
	function _getEntities($entityGuids) {
		return arms_util_array_cloneAll(
			arms_util_array_keyslice($this->entities, $entityGuids)
		);
	}

	/**
	 * Save an entity
	 *
	 * @param $entities array(YASS_Entity)
	 */
	function _putEntities($entities) {
		foreach ($entities as $entity) {
			if ($entity->exists) {
				$this->entities[$entity->entityGuid] = $entity;
			} else {
				unset($this->entities[$entity->entityGuid]);
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
		return $this->entities;
	}
}


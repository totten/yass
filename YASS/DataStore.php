<?php

require_once 'YASS/Entity.php';

abstract class YASS_DataStore {
	/**
	 * @var string, GUID
	 */
	var $replicaId;
	
	/**
	 * Get the content of an entity
	 *
	 * @return YASS_Entity
	 */
	abstract function getEntity($entityType, $entityGuid);

	/**
	 * Save an entity
	 */
	abstract function putEntity(YASS_Entity $entity);
	
	/**
	 * Get a list of all entities
	 *
	 * This is an optional interface to facilitate testing/debugging
	 *
	 * @return array(entityType => array(entityGuid => YASS_Entity))
	 */
	function getAllEntitiesDebug() {
		return FALSE;
	}
}

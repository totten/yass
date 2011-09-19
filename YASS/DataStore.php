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
}

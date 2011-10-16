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
	 * @param $entityGuids array(entityGuid)
	 * @return array(entityGuid => YASS_Entity)
	 */
	abstract function getEntities($entityGuids);

	/**
	 * Save an entity
	 *
	 * @param $entities array(YASS_Entity)
	 */
	abstract function putEntities($entities);
	
	/**
	 * Get a list of all entities
	 *
	 * This is an optional interface to facilitate testing/debugging
	 *
	 * @return array(entityGuid => YASS_Entity)
	 */
	function getAllEntitiesDebug() {
		return FALSE;
	}
}

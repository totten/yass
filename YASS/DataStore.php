<?php

require_once 'YASS/ReplicaListener.php';
require_once 'YASS/Entity.php';

abstract class YASS_DataStore extends YASS_ReplicaListener {
	/**
	 * @var YASS_Replica
	 */
	var $replica;
	
	/**
	 * Get the content of an entity
	 *
	 * @param $entityGuid string
	 * @return YASS_Entity or FALSE
	 */
	function getEntity($entityGuid) {
	  $entities = $this->getEntities(array($entityGuid));
	  return $entities[$entityGuid];
	}
	 
	/**
	 * Get the content of several entities
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

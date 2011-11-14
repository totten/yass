<?php

require_once 'YASS/ReplicaListener.php';
require_once 'YASS/Entity.php';

abstract class YASS_DataStore extends YASS_ReplicaListener {
	/**
	 * @var YASS_Replica
	 */
	var $replica;
	 
	/**
	 * Get the content of several entities
	 *
	 * @param $entityGuids array(entityGuid)
	 * @return array(entityGuid => YASS_Entity)
	 */
	function getEntities($entityGuids) {
		$entities = $this->_getEntities($entityGuids);
		foreach ($this->replica->filters as $filter) {
			$filter->toGlobal($entities, $this->replica);
		}
		return $entities;
	}

	/**
	 * Get the content of several entities
	 *
	 * @param $entityGuids array(entityGuid)
	 * @return array(entityGuid => YASS_Entity)
	 */
	abstract function _getEntities($entityGuids);

	/**
	 * Save an entity
	 *
	 * @param $entities array(YASS_Entity)
	 */
	function putEntities($entities) {
		foreach (array_reverse($this->replica->filters) as $filter) {
			$filter->toLocal($entities, $this->replica);
		}
		return $this->_putEntities($entities);
	}
	
	/**
	 * Save an entity
	 *
	 * @param $entities array(YASS_Entity)
	 */
	abstract function _putEntities($entities);
	
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

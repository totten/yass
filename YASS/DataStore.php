<?php

require_once 'YASS/Entity.php';
require_once 'YASS/IReplicaListener.php';

abstract class YASS_DataStore implements YASS_IReplicaListener {
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
	
	function onPostJoin(YASS_Replica $replica, YASS_Replica $master) {}
	function onPostRejoin(YASS_Replica $replica, YASS_Replica $master) {}
	function onPostReset(YASS_Replica $replica, YASS_Replica $master) {}
	function onPostSync(YASS_Replica $replica) {}
	function onPreJoin(YASS_Replica $replica, YASS_Replica $master) {}
	function onPreRejoin(YASS_Replica $replica, YASS_Replica $master) {}
	function onPreReset(YASS_Replica $replica, YASS_Replica $master) {}
	function onPreSync(YASS_Replica $replica){}
}

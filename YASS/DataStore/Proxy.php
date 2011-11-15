<?php

require_once 'YASS/Proxy.php';
require_once 'YASS/Entity.php';
require_once 'YASS/IDataStore.php';

/**
 * A datastore which uses arms_interlink RPC calls to synchronize against a remote datastore
 */
class YASS_DataStore_Proxy extends YASS_Proxy implements YASS_IDataStore {

	/**
	 * @var YASS_Replica
	 */
	var $replica;
	
	/**
	 * 
	 */
	public function __construct(YASS_Replica $replica) {
		module_load_include('service.inc', 'yass');
		$this->replica = $replica;
		parent::__construct($replica->spec['remoteSite'], $replica->spec['remoteReplica']);
	}
	 
	/**
	 * Get the content of several entities
	 *
	 * @param $entityGuids array(entityGuid)
	 * @return array(entityGuid => YASS_Entity)
	 */
	function getEntities($entityGuids) {
		$result = $this->_proxy('yass.getEntities', $entityGuids);
		YASS_Proxy::decodeAllInplace('YASS_Entity', $result);
		return $result;
	}

	/**
	 * Save an entity
	 *
	 * @param $entities array(YASS_Entity)
	 */
	function putEntities($entities) {
		YASS_Proxy::encodeAllInplace('YASS_Entity', $entities);
		$this->_proxy('yass.putEntities', $entities);
	}
	
}

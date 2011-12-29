<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/Replica.php';

class YASS_DataStore_GenericSQL extends YASS_DataStore {

	/**
	 * 
	 */
	public function __construct(YASS_Replica $replica) {
		arms_util_include_api('query');
		$this->replica = $replica;
	}
	
	/**
	 * Get the content of an entity
	 *
	 * @param $entityGuids array(entityGuid)
	 * @return array(entityGuid => YASS_Entity)
	 */
	function _getEntities($entityGuids) {
		if (empty($entityGuids)) {
			return array();
		}
		$select = arms_util_query('{yass_datastore} ds');
		$select->addSelects(array('ds.entity_id entity_id','ds.entity_type entity_type','ds.data data'));
		$select->addWheref('ds.replica_id=%d', $this->replica->id);
		$select->addWhere(arms_util_query_in('ds.entity_id', $entityGuids));
		if ($this->replica->accessControl && !YASS_Context::get('disableAccessControl')) {
			$pairing = YASS_Context::get('pairing');
			if (!$pairing) {
				throw new Exception('Failed to locate active replica pairing');
			}
			$partnerReplica = $pairing->getPartner($this->replica->id);
			if (!$partnerReplica) {
				throw new Exception('Failed to locate partner replica');
			}
			$select->addJoinf('INNER JOIN {yass_ace} ace 
				ON ace.replica_id = ds.replica_id 
				AND ace.guid = ds.entity_id
				AND ace.client_replica_id=%d
				AND ace.is_allowed = 1',
				$partnerReplica->id);
		}
		
		$q = db_query($select->toSQL());
		$result = array();
		while ($row = db_fetch_object($q)) {
			$result[$row->entity_id] = $this->toYassEntity($row);
		}
		
		/*
		// Mix-in #acl
		if ($this->replica->accessControl && !YASS_Context::get('disableAccessControl')) {
			$pairing = YASS_Context::get('pairing');
			if (!$pairing) {
				throw new Exception('Failed to locate active replica pairing');
			}
			$partnerReplica = $pairing->getPartner($this->replica->id);
			if (!$partnerReplica) {
				throw new Exception('Failed to locate partner replica');
			}
			
			$select = arms_util_query('{yass_ace}');
			$select->addSelects(array('replica_id','guid','client_replica_id'));
			$select->addWheref('replica_id=%d', $this->replica->id);
			$select->addWhere(arms_util_query_in('entity_id', $entityGuids));
			$select->addWheref('client_replica_id=%d', $partnerReplica->id);
			$q = db_query($select->toSQL());
			while ($row = db_fetch_object($q)) {
			}
		}
		*/
		return $result;
	}

	/**
	 * Save an entity
	 *
	 * @param $entities array(YASS_Entity)
	 */
	function _putEntities($entities) {
		foreach ($entities as $entity) {
			if ($this->replica->accessControl && !YASS_Context::get('disableAccessControl') && $entity->data['#acl']) {
				$this->_putAcl($entity->entityGuid, $entity->data['#acl']);
			}
			$serializedData = serialize($entity->data);
			db_query('INSERT INTO {yass_datastore} (replica_id,entity_type,entity_id,data)
				VALUES (%d,"%s","%s","%s")
				ON DUPLICATE KEY UPDATE data="%s"',
				$this->replica->id, $entity->entityType, $entity->entityGuid, $serializedData,
				$serializedData);
		}
	}
	
	/**
	 * Set the access-control list for an entity
	 *
	 * @param $acl array(replicaId) white-list
	 */
	private function _putAcl($entityGuid, $acl) {
		if (empty($acl)) {
			db_query('UPDATE {yass_ace} SET is_allowed = 0 WHERE replica_id = %d AND guid = "%s"', $this->replica->id, $entityGuid);
			return;
		}
		
		$aclString = implode(',', array_filter($acl, 'is_numeric'));
		db_query('UPDATE {yass_ace} SET is_allowed = 0 WHERE replica_id = %d AND guid = "%s" AND client_replica_id NOT IN ('.$aclString.')',
			$this->replica->id, $entityGuid);
		
		foreach ($acl as $clientReplicaId) {
			db_query('INSERT INTO {yass_ace} (replica_id,guid,client_replica_id,is_allowed) VALUES (%s,"%s",%d,1)
				ON DUPLICATE KEY UPDATE is_allowed = 1',
				$this->replica->id, $entityGuid, $clientReplicaId
			);
		}
	}
	
	/**
	 * Get a list of all entities
	 *
	 * This is an optional interface to facilitate testing/debugging
	 *
	 * @return array(entityGuid => YASS_Entity)
	 */
	function getAllEntitiesDebug()
	{
		$q = db_query('SELECT entity_id, entity_type, data FROM {yass_datastore} WHERE replica_id=%d',
			$this->replica->id);
		$entities = array();
		while ($row = db_fetch_object($q)) {
			$entities[ $row->entity_id ] = $this->toYassEntity($row);
		}
		return $entities;
	}
	
	/**
	 * Map a SQL row to an object
	 *
	 * @param $row stdClass{yass_datastore}
	 * @return YASS_Entity
	 */
	protected function toYassEntity($row) {
		return new YASS_Entity($row->entity_id, $row->entity_type, unserialize($row->data));
	}
	
	function onChangeId(YASS_Replica $replica, $oldId, $newId) {
		db_query('UPDATE {yass_datastore} SET replica_id=%d WHERE replica_id=%d', $newId, $oldId);
	}
}


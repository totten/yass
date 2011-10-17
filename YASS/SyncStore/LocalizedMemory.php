<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';

class YASS_SyncStore_LocalizedMemory extends YASS_SyncStore {

	var $replicaId;
	
	/**
	 * @var array(replicaId => YASS_Version)
	 */
	var $lastSeen;
	
	/**
	 * @var array(entityType => array(lid => YASS_SyncState))
	 */
	var $syncStates;
	
	/**
	 * 
	 * @param $replicaSpec array{yass_replicas} Specification for the replica
	 */
	public function __construct($replicaSpec) {
		$this->replicaId = $replicaSpec['id'];
		$this->lastSeen = array($this->replicaId => new YASS_Version($this->replicaId, 0));
		$this->syncStates = array();
	}

	/**
	 * Find a list of revisions that have been previously applied to a replica
	 *
	 * @return array(replicaId => YASS_Version)
	 */
	function getLastSeenVersions() {
		return $this->lastSeen;
	}
	
	/**
	 * Assert that the given replica includes the data for (replica,tick)
	 */
	function markSeen(YASS_Version $lastSeen) {
		if (!$this->lastSeen[$lastSeen->replicaId]
			|| $lastSeen->tick > $this->lastSeen[$lastSeen->replicaId]->tick 
		) {
			$this->lastSeen[ $lastSeen->replicaId ] = $lastSeen;
		}
	}
	
	/**
	 * Find all records in a replica which have been modified since the given point
	 *
	 * @return array(entityGuid => YASS_SyncState)
	 */
	function getModified(YASS_Version $lastSeen = NULL) {
		$mapper = YASS_Engine::singleton()->getReplicaById($this->replicaId)->mapper;
		$modified = array();
		if (!$lastSeen) {
			foreach ($this->syncStates as $type => $syncStates) {
				$modified = array_merge($modified, array_values($syncStates));
			}
		} else {
			foreach ($this->syncStates as $type => $syncStates) {
				foreach ($syncStates as $lid => $syncState) {
					if ($syncState->modified->replicaId == $lastSeen->replicaId
						&& $syncState->modified->tick > $lastSeen->tick
					) {
						$modified[] = $syncState;
					}
				}
			}
		}
		
		$result = arms_util_array_index(array('entityGuid'), $modified);
		return $result;
	}
	
	
	/**
	 *
	 */
	function onUpdateEntity($entityGuid) {
		// update tick count
		if ($this->lastSeen[$this->replicaId]) {
			$this->lastSeen[$this->replicaId] = $this->lastSeen[$this->replicaId]->next();
		} else {
			$this->lastSeen[$this->replicaId] = new YASS_Version($this->replicaId, 1);
		}
		$this->setSyncState($entityGuid, $this->lastSeen[$this->replicaId]);
	}
	
	/**
	 * Determine the sync state of a particular entity
	 *
	 * @return YASS_SyncState
	 */
	function getSyncState($entityGuid) {
		$mapper = YASS_Engine::singleton()->getReplicaById($this->replicaId)->mapper;
		list ($type, $lid) = $mapper->toLocal($entityGuid);
		if (!($type && $lid)) {
			return FALSE;
		}
		return $this->syncStates[$type][$lid];
	}
	
	/**
	 * Set the sync state of an entity
	 */
	function setSyncState($entityGuid, YASS_Version $modified) {
		$mapper = YASS_Engine::singleton()->getReplicaById($this->replicaId)->mapper;
		list ($type, $lid) = $mapper->toLocal($entityGuid);
		if (!($type && $lid)) {
			throw new Exception(sprintf('Failed to store state for unmapped entity (GUID=%s). DataStore should have mapped GUID to local ID.', $entityGuid));
		}
		
		// update tick count
		if ($this->syncStates[$type][$lid]) {
			$this->syncStates[$type][$lid]->modified = $modified;
		} else {
			$this->syncStates[$type][$lid] = new YASS_SyncState($entityGuid, 
				$modified, $modified);
		}
	}
}

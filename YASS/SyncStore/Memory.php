<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/Replica.php';
require_once 'YASS/SyncStore.php';

class YASS_SyncStore_Memory extends YASS_SyncStore {

	var $replicaId;
	
	/**
	 * @var array(replicaId => YASS_Version)
	 */
	var $lastSeen;
	
	/**
	 * @var array(guid => YASS_SyncState)
	 */
	var $syncStates;
	
	/**
	 * 
	 */
	public function __construct(YASS_Replica $replica) {
		$this->replicaId = $replica->id;
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
		if (!$lastSeen) {
			return $this->syncStates;
		} else {
			$modified = array();
			foreach ($this->syncStates as $entityGuid => $syncState) {
				if ($syncState->modified->replicaId == $lastSeen->replicaId
					&& $syncState->modified->tick > $lastSeen->tick
				) {
					$modified[$entityGuid] = $syncState;
				}
			}
			return $modified;
		}
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
		return $this->syncStates[$entityGuid];
	}
	
	/**
	 * Set the sync state of an entity
	 */
	function setSyncState($entityGuid, YASS_Version $modified) {
		// update tick count
		if ($this->syncStates[$entityGuid]) {
			$this->syncStates[$entityGuid]->modified = $modified;
		} else {
			$this->syncStates[$entityGuid] = new YASS_SyncState($entityGuid, 
				$modified, $modified);
		}
	}
}

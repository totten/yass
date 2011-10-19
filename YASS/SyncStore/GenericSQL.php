<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/Replica.php';
require_once 'YASS/SyncStore.php';

class YASS_SyncStore_GenericSQL extends YASS_SyncStore {

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
		$this->replica = $replica;
		$lastSeen = $this->getLastSeenVersions();
		if (! $lastSeen[$this->replica->id]) {
			$this->markSeen(new YASS_Version($this->replica->id, 0));
		}
		$this->syncStates = array();
	}

	/**
	 * Find a list of revisions that have been previously applied to a replica
	 *
	 * @return array(replicaId => YASS_Version)
	 */
	function getLastSeenVersions() {
		if (!is_array($this->lastSeen)) {
			$q = db_query('SELECT r_replica_id, r_tick FROM {yass_syncstore_seen} WHERE replica_id = %d', $this->replica->id);
			$this->lastSeen = array();
			while ($row = db_fetch_object($q)) {
				$this->lastSeen[ $row->r_replica_id ] = new YASS_Version($row->r_replica_id, $row->r_tick);
			}
		}
		return $this->lastSeen;
	}
	
	/**
	 * Assert that the given replica includes the data for (replica,tick)
	 */
	function markSeen(YASS_Version $lastSeen) {
		$this->getLastSeenVersions(); // fill cache
		if (!$this->lastSeen[$lastSeen->replicaId]
			|| $lastSeen->tick > $this->lastSeen[$lastSeen->replicaId]->tick 
		) {
			db_query('INSERT INTO {yass_syncstore_seen} (replica_id, r_replica_id, r_tick) 
			  VALUES (%d, %d, %d)
			  ON DUPLICATE KEY UPDATE r_tick = %d
			', $this->replica->id, $lastSeen->replicaId, $lastSeen->tick, $lastSeen->tick);
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
			$q = db_query('SELECT replica_id, entity_id, u_replica_id, u_tick, c_replica_id, c_tick
				FROM {yass_syncstore_state}
				WHERE replica_id=%d AND u_replica_id=%d',
				$this->replica->id, $this->replica->id);
		} else {
			$q = db_query('SELECT replica_id, entity_id, u_replica_id, u_tick, c_replica_id, c_tick
				FROM yass_syncstore_state
				WHERE replica_id = %d AND u_replica_id = %d AND u_tick > %d',
				$this->replica->id, $lastSeen->replicaId, $lastSeen->tick);
		}

		$modified = array();
		while ($row = db_fetch_object($q)) {
			$modified[ $row->entity_id ] = $this->toYassSyncState($row);
		}
		return $modified;
	}
	
	
	/**
	 *
	 */
	function onUpdateEntity($entityGuid) {
		// update tick count
		$this->getLastSeenVersions(); // fill cache
		if ($this->lastSeen[$this->replica->id]) {
			$this->markSeen($this->lastSeen[$this->replica->id]->next());
		} else {
			$this->markSeen(new YASS_Version($this->replica->id, 1));
		}
		$this->setSyncState($entityGuid, $this->lastSeen[$this->replica->id]);
	}
	
	/**
	 * Determine the sync state of a particular entity
	 *
	 * @return YASS_SyncState
	 */
	function getSyncState($entityGuid) {
		$q = db_query('SELECT replica_id, entity_id, u_replica_id, u_tick, c_replica_id, c_tick 
			FROM {yass_syncstore_state}
			WHERE replica_id=%d
			AND entity_id="%s"',
			$this->replica->id, $entityGuid);
		while ($row = db_fetch_object($q)) {
			return $this->toYassSyncState($row);
		}
		return FALSE;
	}
	
	/**
	 * Set the sync state of an entity
	 */
	function setSyncState($entityGuid, YASS_Version $modified) {
		// update tick count
		$row = array(
			'replica_id' => $this->replica->id,
			'entity_id' => $entityGuid,
			'u_replica_id' => $modified->replicaId,
			'u_tick' => $modified->tick,
		);
		if ($this->getSyncState($entityGuid)) {
			drupal_write_record('yass_syncstore_state', $row, array('replica_id','entity_id'));
		} else {
			$row['c_replica_id'] = $modified->replicaId;
			$row['c_tick'] =$modified->tick;
			drupal_write_record('yass_syncstore_state', $row);
		}
	}
	
	/**
	 * Destroy any last-seen or sync-state data
	 */
	function destroy() {
		db_query('DELETE FROM {yass_syncstore_seen} WHERE replica_id=%d', $this->replica->id);
		db_query('DELETE FROM {yass_syncstore_state} WHERE replica_id=%d', $this->replica->id);
	}
	
	/**
	 * Convert a SQL row to an object
	 *
	 * @param stdClass{yass_syncstore_state}
	 * @return YASS_SyncState
	 */
	protected function toYassSyncState($row) {
		return new YASS_SyncState($row->entity_id,
			new YASS_Version($row->u_replica_id, $row->u_tick),
			new YASS_Version($row->c_replica_id, $row->c_tick)
		);
	}
}

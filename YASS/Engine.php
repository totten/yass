<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/ConflictResolver.php';
require_once 'YASS/Replica.php';

class YASS_Engine {
	static $_singleton;
	static function singleton($fresh = FALSE) {
		if ($fresh || ! self::$_singleton) {
			self::$_singleton = new YASS_Engine();
		}
		return self::$_singleton;
	}

	/**
	 * @var array(replicaName => array{yass_replicas})
	 */
	var $_replicaMetadata;
	
	/**
	 * @var array(replicaId => YASS_Replica)
	 */
	var $_replicas;

	/**
	 * Add a (non-persistent) replica
	 *
	 * @param $replica YASS_Replica
	 */
	function addReplica(YASS_Replica $replica) {
		$this->getReplicas();
		$this->_replicas[$replica->id] = $replica;
	}
	

	/**
	 * Get a list of replicas
	 *
	 * @return array(replicaId => YASS_Replica)
	 */
	function getReplicas($fresh = FALSE) {
		if (!$fresh && is_array($this->_replicas)) {
			return $this->_replicas;
		}
		
		require_once 'YASS/Replica/Persistent.php';
		$metadata = $this->getReplicaMetadata(); // array(replicaName => array{yass_replicas})
		$this->_replicas = array(); // array(replicaId => YASS_Replica)
		foreach ($metadata as $replicaSpec) {
			if ($replicaSpec['is_active']) {
				$this->_replicas[$replicaSpec->id] = new YASS_Replica_Persistent($replicaSpec);
			}
		}
		return $this->_replicas;
	}
	
	/**
	 * Get the handle for a specific replica
	 *
	 * @param $id int
	 * @return YASS_Replica or FALSE
	 */
	function getReplicaById($id) {
		$replicas = $this->getReplicas();
		return $replicas[$id];
	}
	
	/**
	 * Get the handle for a specific replica
	 *
	 * @param $name string
	 * @return YASS_Replica or FALSE
	 */
	function getReplicaByName($name) {
		$replicas = $this->getReplicas();
		foreach ($replicas as $replica) {
			if ($replica->name == $name) {
				return $replica;
			}
		}
		return FALSE;
	}

	/**
	 * Get a list of replicas
	 *
	 * @return array(replicaName => array{yass_replicas})
	 */
	function getReplicaMetadata($fresh = FALSE) {
		if (!$fresh && is_array($this->_replicaMetadata)) {
			return $this->_replicaMetadata;
		}
		
		$this->_replicaMetadata = array(); // array(replicaName => array{yass_replicas})
		$q = db_query('SELECT id, name, is_active, datastore, syncstore, extra FROM {yass_replicas} ORDER BY name');
		while ($row = db_fetch_array($q)) {
			$row = arms_util_xt_parse('yass_replicas', $row);
			$this->_replicaMetadata[$row['name']] = $row;
		}
		return $this->_replicaMetadata;
	}
	
	/**
	 * Add or modify metadata for replicas
	 *
	 * @param $newMetadata array(array{yass_replicas}); each item *must* include 'name'
	 */
	function updateReplicaMetadata($newMetadata) {
		$oldMetadata = $this->getReplicaMetadata();
		foreach ($newMetadata as $replicaSpec) {
			if (empty($replicaSpec['name'])) {
				continue;
			}
			if (isset($oldMetadata[$replicaSpec['name']])) {
				$replicaSpec = array_merge($oldMetadata[$replicaSpec['name']], $replicaSpec);
			}
			arms_util_xt_save('yass_replicas', $replicaSpec);
		}
		$this->_replicaMetadata = FALSE;
	}
	
	/**
	 * Remove all replicas and ancilliary data
	 */
	function destroyReplicas() {
		$this->_replicaMetadata = FALSE;
		$this->_replicas = FALSE;
		db_query('DELETE FROM {yass_replicas}');
		$this->_gc();
		yass_arms_clear();
	}
	
	/**
	 * Destroy an individual replica
	 *
	 * @param $name string
	 */
	function destroyReplica(YASS_Replica $replica) {
		db_query('DELETE FROM {yass_replicas} WHERE name = "%s"', $replica->name);
		if ($replica->name && is_array($this->_replicaMetadata)) {
			unset($this->_replicaMetadata[$replica->name]);
		}
		if ($replica->id && is_array($this->_replicas)) {
			unset($this->_replicas[$replica->id]);
		}
		$this->_gc();
	}
	
	/**
	 * Garbage-collect replica references
	 *
	 * Drupal Schema API doens't support foreign keys -- let alone cascade deletes. So we have
	 * to manually maintain referential integrity.
	 */
	protected function _gc() {
		$replicaIds = array_keys($this->getReplicas());
		if (empty($replicaIds)) {
			$where = '';
		} else {
			$where = 'WHERE replica_id NOT IN (' . implode(',', array_filter($replicaIds, 'is_numeric')) . ')';
		}
		foreach (array('yass_datastore', 'yass_guidmap', 'yass_syncstore_seen', 'yass_syncstore_state') as $table) {
			db_query('DELETE FROM {' . $table . '} ' . $where);
		}
	}
	
	/**
	 * Perform a bi-directional synchronization
	 *
	 * @return YASS_Algorithm_Bidir (completed)
	 */
	function bidir(
		YASS_Replica $src, YASS_Replica $dest,
		YASS_ConflictResolver $conflictResolver
	) {
		require_once 'YASS/Algorithm/Bidir.php';
		$job = new YASS_Algorithm_Bidir();
		$job->run($src->data, $src->sync, $dest->data, $dest->sync, $conflictResolver);
		return $job;
	}

	/**
	 * Submit all data from replica to master, adding all records as new items. Destroys existing ID-GUID mappings.
	 */	
	function join(YASS_Replica $replica, YASS_Replica $master) {
		throw new Exception("FIXME: Clear replica's sync store and GUID mappings. Re-initialize syncstates with increased versions.\n");
		//$this->bidir($replica, $master, new YASS_ConflictResolver_Exception());
		//$this->updateReplicaMetadata(array(
		//  array('name' => $name, 'is_active' => TRUE),
		//));
	}
	
	/**
	 * Submit all data from replica to master, overwriting discrepancies in the master. Relies on existing ID-GUID mappings.
	 */
	function rejoin(YASS_Replica $replica, YASS_Replica $master) {
		throw new Exception("FIXME: Clear replica's sync store. Re-initialize syncstates with increased versions.\n");
		//$this->bidir($replica, $master, new YASS_ConflictResolver_Exception());
		//$this->updateReplicaMetadata(array(
		//  array('name' => $name, 'is_active' => TRUE),
		//));
	}
	
	/**
	 * Submit all data from master to replica, overwriting discrepancies in the replica. Relies on existing ID-GUID mappings.
	 */
	function reset(YASS_Replica $replica, YASS_Replica $master) {
		throw new Exception("FIXME: Clear out data store, sync store\n");
		//$this->bidir($replica, $master, new YASS_ConflictResolver_Exception());
		//$this->updateReplicaMetadata(array(
		//  array('name' => $name, 'is_active' => TRUE),
		//));
	}
}

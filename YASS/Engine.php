<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/ConflictResolver.php';
require_once 'YASS/Replica.php';

class YASS_Engine {
	static $_replicaMetadata;
	static $_replicas;

	/**
	 * Add a (non-persistent) replica
	 *
	 * @param $replica YASS_Replica
	 */
	static function addReplica(YASS_Replica $replica) {
		self::getReplicas();
		self::$_replicas[$replica->id] = $replica;
	}
	

	/**
	 * Get a list of replicas
	 *
	 * @return array(replicaId => YASS_Replica)
	 */
	static function getReplicas($fresh = FALSE) {
		if (!$fresh && is_array(self::$_replicas)) {
			return self::$_replicas;
		}
		
		require_once 'YASS/Replica/Persistent.php';
		$metadata = self::getReplicaMetadata(); // array(replicaName => array{yass_replicas})
		self::$_replicas = array(); // array(replicaId => YASS_Replica)
		foreach ($metadata as $replicaSpec) {
			if ($replicaSpec['is_active']) {
				self::$_replicas[$replicaSpec->id] = new YASS_Replica_Persistent($replicaSpec);
			}
		}
		return self::$_replicas;
	}
	
	/**
	 * Get the handle for a specific replica
	 *
	 * @param $id int
	 * @return YASS_Replica or FALSE
	 */
	static function getReplicaById($id) {
		$replicas = self::getReplicas();
		return $replicas[$id];
	}
	
	/**
	 * Get the handle for a specific replica
	 *
	 * @param $name string
	 * @return YASS_Replica or FALSE
	 */
	static function getReplicaByName($name) {
		$replicas = self::getReplicas();
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
	static function getReplicaMetadata($fresh = FALSE) {
		if (!$fresh && is_array(self::$_replicaMetadata)) {
			return self::$_replicaMetadata;
		}
		
		self::$_replicaMetadata = array(); // array(replicaName => array{yass_replicas})
		$q = db_query('SELECT id, name, is_active, datastore, syncstore, extra FROM {yass_replicas} ORDER BY name');
		while ($row = db_fetch_array($q)) {
			$row = arms_util_xt_parse('yass_replicas', $row);
			self::$_replicaMetadata[$row['name']] = $row;
		}
		return self::$_replicaMetadata;
	}
	
	/**
	 * Add or modify metadata for replicas
	 *
	 * @param $newMetadata array(array{yass_replicas}); each item *must* include 'name'
	 */
	static function updateReplicaMetadata($newMetadata) {
		$oldMetadata = self::getReplicaMetadata();
		foreach ($newMetadata as $replicaSpec) {
			if (empty($replicaSpec['name'])) {
				continue;
			}
			if (isset($oldMetadata[$replicaSpec['name']])) {
				$replicaSpec = array_merge($oldMetadata[$replicaSpec['name']], $replicaSpec);
			}
			arms_util_xt_save('yass_replicas', $replicaSpec);
		}
		self::$_replicaMetadata = FALSE;
	}
	
	/**
	 * Remove all replicas and ancilliary data
	 */
	static function destroyReplicas() {
		self::$_replicaMetadata = FALSE;
		self::$_replicas = FALSE;
		db_query('DELETE FROM {yass_replicas}');
		self::_gc();
		yass_arms_clear();
	}
	
	/**
	 * Destroy an individual replica
	 *
	 * @param $name string
	 */
	static function destroyReplica(YASS_Replica $replica) {
		db_query('DELETE FROM {yass_replicas} WHERE name = "%s"', $replica->name);
		if ($replica->name && is_array(self::$_replicaMetadata)) {
			unset(self::$_replicaMetadata[$replica->name]);
		}
		if ($replica->id && is_array(self::$_replicas)) {
			unset(self::$_replicas[$replica->id]);
		}
		self::_gc();
	}
	
	/**
	 *
	 */

	/**
	 * Garbage-collect replica references
	 *
	 * Drupal Schema API doens't support foreign keys -- let alone cascade deletes. So we have
	 * to manually maintain referential integrity.
	 */
	protected static function _gc() {
		$replicaIds = array_keys(self::getReplicas());
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
	static function bidir(
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
	static function join(YASS_Replica $replica, YASS_Replica $master) {
		throw new Exception("FIXME: Clear replica's sync store and GUID mappings. Re-initialize syncstates with increased versions.\n");
		//self::bidir($replica, $master, new YASS_ConflictResolver_Exception());
		//self::updateReplicaMetadata(array(
		//  array('name' => $name, 'is_active' => TRUE),
		//));
	}
	
	/**
	 * Submit all data from replica to master, overwriting discrepancies in the master. Relies on existing ID-GUID mappings.
	 */
	static function rejoin(YASS_Replica $replica, YASS_Replica $master) {
		throw new Exception("FIXME: Clear replica's sync store. Re-initialize syncstates with increased versions.\n");
		//self::bidir($replica, $master, new YASS_ConflictResolver_Exception());
		//self::updateReplicaMetadata(array(
		//  array('name' => $name, 'is_active' => TRUE),
		//));
	}
	
	/**
	 * Submit all data from master to replica, overwriting discrepancies in the replica. Relies on existing ID-GUID mappings.
	 */
	static function reset(YASS_Replica $replica, YASS_Replica $master) {
		throw new Exception("FIXME: Clear out data store, sync store\n");
		//self::bidir($replica, $master, new YASS_ConflictResolver_Exception());
		//self::updateReplicaMetadata(array(
		//  array('name' => $name, 'is_active' => TRUE),
		//));
	}
}

<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/ConflictResolver.php';
require_once 'YASS/Replica.php';

class YASS_Engine {
	static $_singleton;
	static function singleton($fresh = FALSE) {
		if ($fresh || ! self::$_singleton) {
			arms_util_include_api('array');
			self::$_singleton = new YASS_Engine();
		}
		return self::$_singleton;
	}
	
	/**
	 * @var array(replicaId => YASS_Replica)
	 */
	var $_replicas;
	
	/**
	 * Register and instantiate a new replica
	 */
	function createReplica($replicaSpec) {
		$this->getReplicas(); // cache
		$replicaSpec = $this->updateReplicaSpec($replicaSpec);
		$this->_replicas[$replicaSpec['id']] = new YASS_Replica($replicaSpec);
		$triggers = array_merge(
			$this->_replicas[$replicaSpec['id']]->onCreateSqlTriggers($this->_replicas[$replicaSpec['id']]),
			$this->_replicas[$replicaSpec['id']]->data->onCreateSqlTriggers($this->_replicas[$replicaSpec['id']]),
			$this->_replicas[$replicaSpec['id']]->sync->onCreateSqlTriggers($this->_replicas[$replicaSpec['id']])
		);
		if (!empty($triggers)) {
			arms_util_include_api('trigger');
			arms_util_trigger_rebuild();
		}
		return $this->_replicas[$replicaSpec['id']];
	}

	/**
	 * Get a list of active replicas
	 *
	 * @return array(replicaId => YASS_Replica)
	 */
	function getActiveReplicas() {
		$this->getReplicas(); // cache
		$result = array();
		foreach ($this->_replicas as $id => $replica) {
			if ($replica->isActive) {
				$result[$id] = $replica;
			}
		}
		return $result;
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
		
		$this->_replicas = array(); // array(replicaId => YASS_Replica)
		$q = db_query('SELECT id, name, is_active, datastore, syncstore, extra FROM {yass_replicas} ORDER BY name');
		while ($row = db_fetch_array($q)) {
			$replicaSpec = arms_util_xt_parse('yass_replicas', $row);
			$this->_replicas[$replicaSpec['id']] = new YASS_Replica($replicaSpec);
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
	 * Add or modify metadata for replicas
	 *
	 * @param $replicaSpec array{yass_replicas}; *must* include 'name'
	 * @param $recreate bool whether to reconstruct the current YASS_Replica 
	 * @return $replicaSpec array{yass_replicas}; fully-formed
	 */
	function updateReplicaSpec($replicaSpec) {
		if (empty($replicaSpec['name'])) {
			return FALSE;
		}
		
		$q = db_query('SELECT id, name, is_active, datastore, syncstore, extra FROM {yass_replicas} WHERE name="%s"', $replicaSpec['name']);
		if ($row = db_fetch_array($q)) {
			$baseline = arms_util_xt_parse('yass_replicas', $row);
		} else {
			$baseline = array(
				'datastore' => FALSE,
				'syncstore' => FALSE,
				'is_active' => FALSE,
			);
		}
		$replicaSpec = array_merge($baseline, $replicaSpec);
		
		arms_util_xt_save('yass_replicas', $replicaSpec);
		return $replicaSpec;
	}
	
	/**
	 * Remove all replicas and ancilliary data
	 */
	function destroyReplicas() {
		$this->_replicas = FALSE;
		db_query('DELETE FROM {yass_replicas}');
		db_query('DELETE FROM {yass_guidmap}');
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
		module_invoke_all('yass_replica', array('op' => 'preSync', 'replica' => &$src));
		module_invoke_all('yass_replica', array('op' => 'preSync', 'replica' => &$dest));

		require_once 'YASS/Algorithm/Bidir.php';
		$job = new YASS_Algorithm_Bidir();
		$job->run($src, $dest, $conflictResolver);
		
		module_invoke_all('yass_replica', array('op' => 'postSync', 'replica' => &$src));
		module_invoke_all('yass_replica', array('op' => 'postSync', 'replica' => &$dest));
		return $job;
	}
	
	/**
	 * Transfer a set of records from one replica to another
	 *
	 * @param $syncStates array(YASS_SyncState) List of entities/revisions to transfer
	 */
	function transfer(
		YASS_Replica $src,
		YASS_Replica $dest,
		$syncStates)
	{
		if (empty($syncStates)) { return; }
		$entities = $src->data->getEntities(arms_util_array_collect($syncStates, 'entityGuid'));
		$dest->data->putEntities($entities);
		foreach ($syncStates as $srcSyncState) {
			$dest->sync->setSyncState($srcSyncState->entityGuid, $srcSyncState->modified);
		}
	}
	
	protected function _changeReplicaId(YASS_Replica $replica) {
		$newSpec = $replica->spec;
		unset($newSpec['id']);
		arms_util_xt_save('yass_replicas', $newSpec);
		
		$oldId = $replica->id;
		$newId = $newSpec['id'];
		
		module_invoke_all('yass_replica', array('op' => 'changeId', 'replica' => &$replica, 'oldId' => $oldId, 'newId' => $newId));
		
		db_query('DELETE FROM {yass_replicas} WHERE id = %d', $oldId);
		
		$replica->id = $newId;
		$replica->spec = $newSpec;
		
		if (is_array($this->_replicas)) {
			unset($this->_replicas[$oldId]);
			$this->_replicas[$newId] = $replica;
		}
	}

	/**
	 * Copy all data between replica and master. Previously synchronized records will become duplicates. Destroys existing ID-GUID mappings.
	 */
	function join(YASS_Replica $replica, YASS_Replica $master) {
		module_invoke_all('yass_replica', array('op' => 'preJoin', 'replica' => &$replica, 'master' => &$master));
		
		// teardown
		if ($replica->spec['is_joined']) {
			// Force replica and master to mutually resend all records by changing the replica ID.
			$replica->sync->destroy();
			$replica->mapper->destroy();
			$this->_changeReplicaId($replica);
		}
		
		// buildup
		$this->bidir($replica, $master, new YASS_ConflictResolver_Exception());
		$replica->spec = $this->updateReplicaSpec(array(
			'name' => $replica->name, 'is_active' => TRUE, 'is_joined' => TRUE,
		));
		
		module_invoke_all('yass_replica', array('op' => 'postJoin', 'replica' => &$replica, 'master' => &$master));
	}
	
	/**
	 * Submit all data from replica to master, overwriting discrepancies in the master. Relies on existing ID-GUID mappings.
	 */
	function rejoin(YASS_Replica $replica, YASS_Replica $master) {
		module_invoke_all('yass_replica', array('op' => 'preRejoin', 'replica' => &$replica, 'master' => &$master));
		
		// teardown
		if ($replica->spec['is_joined']) {
			// Force replica to resend all its records to master, et al

			// hack/mitigation: sync everything except $replica with $master to reduce chance of conflicts
			$this->_syncAll($master, new YASS_ConflictResolver_Exception(), array($master->id, $replica->id));
			$replica->sync->updateAllVersions();
		}
		
		// buildup
		$this->bidir($replica, $master, new YASS_ConflictResolver_Exception());
		$replica->spec = $this->updateReplicaSpec(array(
			'name' => $replica->name, 'is_active' => TRUE, 'is_joined' => TRUE,
		));
		
		module_invoke_all('yass_replica', array('op' => 'postRejoin', 'replica' => &$replica, 'master' => &$master));
	}
	
	/**
	 * Submit all data from master to replica, overwriting discrepancies in the replica. Relies on existing ID-GUID mappings.
	 */
	function reset(YASS_Replica $replica, YASS_Replica $master) {
		module_invoke_all('yass_replica', array('op' => 'preRejoin', 'replica' => &$replica, 'master' => &$master));
		
		// teardown
		if ($replica->spec['is_joined']) {
			// Force master to resend all data to replica
			
			/* 
			// APPROACH A: Flag everything on the master as updated. This has the unfortunate sideeffect of
			// causing all replicas to resync all entities -- which increases the odds of (unnecessary) conflicts.
			
			// hack/mitigation: sync everything except $replica with $master to reduce chance of conflicts
			$this->_syncAll($master, $conflictResolver, array($master->id, $replica->id));
			$master->sync->updateAllVersions();
			*/
			
			// APPROACH B: Destroy $replica sync state so that $replica believes it hasn't seen anything
			// from master. At the same time, rename $replica's head from (oldId,oldTick) to (newId,0);
			// this will make master believe that the replica has no interesting data.
			// Note: When rolling-back tickcount, we must change replicaId to ensure future updates
			// propagate.
			$replica->sync->destroy();
			$this->_changeReplicaId($replica);
		}
		
		// buildup
		$this->bidir($replica, $master, new YASS_ConflictResolver_Exception());
		$replica->spec = $this->updateReplicaSpec(array(
			'name' => $replica->name, 'is_active' => TRUE, 'is_joined' => TRUE,
		));
		
		module_invoke_all('yass_replica', array('op' => 'postRejoin', 'replica' => &$replica, 'master' => &$master));
	}
	
	/**
	 * Synchronize all replicas with a master
	 */
	function syncAll(YASS_Replica $master, YASS_ConflictResolver $conflictResolver) {
		for ($i = 0; $i < 2; $i++) {
			$this->_syncAll($master, $conflictResolver, array($master->id));
		}
	}
	
	/**
	 * Synchronize all replicas
	 *
	 * @param $excludes array(replicaId)
	 */
	protected function _syncAll(YASS_Replica $master, YASS_ConflictResolver $conflictResolver, $excludes) {
		foreach ($this->getActiveReplicas() as $replica) {
			if (in_array($replica->id, $excludes)) {
				continue;
			}
			$this->bidir($replica, $master, $conflictResolver);
		}
	}
	
	function createGuid() {
		$domain = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-';
		$result = '';
		for ($i = 0; $i < 32; $i++) {
			$r = rand(0, strlen($domain) - 1);
			$result = $result . $domain{$r};
		}
		return $result;
	}
}

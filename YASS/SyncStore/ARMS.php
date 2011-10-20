<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/Replica.php';
require_once 'YASS/SyncStore/GenericSQL.php';

class YASS_SyncStore_ARMS extends YASS_SyncStore_GenericSQL {

	/**
	 * 
	 */
	public function __construct(YASS_Replica $replica) {
		parent::__construct($replica);
	}

	/**
	 * Implementation of hook_arms_trigger
	 */
	function onCreateSqlTriggers(YASS_Replica $replica) {
		// make sure that there's a last-seen value for the trigger to read
		db_query('INSERT IGNORE INTO {yass_syncstore_seen} (replica_id,r_replica_id,r_tick) VALUES (%d,%d,%d)',
			$replica->id, $replica->id, 0);
	
		$template = '
				SET @yass_nextTick = 1+(SELECT max(r_tick) FROM yass_syncstore_seen
					WHERE replica_id = @yass_replicaId AND r_replica_id = @yass_replicaId LIMIT 1);
				UPDATE yass_syncstore_seen SET r_tick = @yass_nextTick
					WHERE replica_id = @yass_replicaId AND r_replica_id = @yass_replicaId;
				INSERT DELAYED INTO yass_syncstore_state (replica_id, entity_type, entity_id, u_replica_id, u_tick, c_replica_id, c_tick) 
				VALUES (@yass_replicaId, "@entityType", {ACTIVE}.@entityIdColumn, @yass_replicaId, @yass_nextTick, @yass_replicaId, @yass_nextTick)
				ON DUPLICATE KEY UPDATE u_replica_id = @yass_replicaId, u_tick = @yass_nextTick
		';
		
		foreach (array('civicrm_contact') as $table) {
			$staticArgs = array(
				'@yass_replicaId' => $replica->id,
				'@entityType' => $table,
				'@entityIdColumn' => 'id',
			);
			$result[] = array(
				'table' => array($table),
				'when' => array('after'),
				'event' => array('insert', 'update', 'delete'),
				'sql' => strtr($template, $staticArgs),
			);
		}
		return $result;
	}
	
}
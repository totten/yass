<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/Replica.php';
require_once 'YASS/SyncStore/GenericSQL.php';

class YASS_SyncStore_ARMS extends YASS_SyncStore_GenericSQL {
	static $ENTITIES = array('civicrm_contact');

	/**
	 * 
	 */
	public function __construct(YASS_Replica $replica) {
		parent::__construct($replica);
		$this->disableCache = TRUE;
	}

	/**
	 * Implementation of hook_arms_trigger
	 */
	function onCreateSqlTriggers(YASS_Replica $replica) {
		// make sure that there's a last-seen value for the trigger to read
		db_query('INSERT IGNORE INTO {yass_syncstore_seen} (replica_id,r_replica_id,r_tick) VALUES (%d,%d,%d)',
			$replica->id, $replica->id, 0);
	
		$template = '
				SET yass_nextTick = 1+(SELECT max(r_tick) FROM yass_syncstore_seen
					WHERE replica_id = @yass_replicaId AND r_replica_id = @yass_replicaId LIMIT 1);
				UPDATE yass_syncstore_seen SET r_tick = yass_nextTick
					WHERE replica_id = @yass_replicaId AND r_replica_id = @yass_replicaId;
				SET yass_guid = (SELECT guid FROM yass_guidmap
					WHERE replica_id = @yass_replicaId AND entity_type = "@entityType" AND lid = {ACTIVE}.@entityIdColumn);
				IF yass_guid IS NULL OR yass_guid = "" THEN
					SET yass_guid = uuid();
					INSERT DELAYED INTO {yass_guidmap} (replica_id,entity_type,lid,guid)
						VALUES (@yass_replicaId, "@entityType", {ACTIVE}.@entityIdColumn, yass_guid);
				END IF;

				INSERT DELAYED INTO yass_syncstore_state (replica_id, entity_type, entity_id, u_replica_id, u_tick, c_replica_id, c_tick) 
				VALUES (@yass_replicaId, "@entityType", yass_guid, @yass_replicaId, yass_nextTick, @yass_replicaId, yass_nextTick)
				ON DUPLICATE KEY UPDATE u_replica_id = @yass_replicaId, u_tick = yass_nextTick
		';
		
		foreach (self::$ENTITIES as $table) {
			$staticArgs = array(
				'@yass_replicaId' => $replica->id,
				'@entityType' => $table,
				'@entityIdColumn' => 'id',
			);
			$result[] = array(
				'table' => array($table),
				'when' => array('after'),
				'event' => array('insert', 'update', 'delete'),
				'declare' => array('yass_nextTick' => 'NUMERIC', 'yass_guid' => 'VARCHAR(36)'),
				'sql' => strtr($template, $staticArgs),
			);
		}
		return $result;
	}
	
	/**
	 * Find any unmapped entities and... map them...
	 */
	function onValidateGuids(YASS_Replica $replica) {
		$engine = YASS_Engine::singleton();
		$this->lastSeen = FALSE; // flush cache
		
		// create GUIDs for any unmapped entities
		foreach (YASS_SyncStore_ARMS::$ENTITIES as $type) {
			$args = array(
				'@yass_replicaId' => $replica->id,
				'@entityType' => $type,
				'@entityIdColumn' => 'id',
			);
			$q = db_query(strtr('
				SELECT 
					"@entityType" AS entity_type,
					entity.@entityIdColumn lid
				FROM {@entityType} entity
				LEFT JOIN {yass_guidmap} map ON (
					map.replica_id = @yass_replicaId 
					AND map.entity_type = "@entityType" 
					AND map.lid = entity.@entityIdColumn
				)
				WHERE map.guid IS NULL
			', $args));
			while ($row = db_fetch_object($q)) {
				$replica->mapper->addMappings(array(
					$type => array($row->lid => $engine->createGuid()),
				));
			}
			
			$q = db_query(strtr('
				SELECT
					map.guid AS guid
				FROM {@entityType} entity
				INNER JOIN {yass_guidmap} map ON (
					map.replica_id = @yass_replicaId
					AND map.entity_type = "@entityType"
					AND map.lid = entity.@entityIdColumn
				)
				LEFT JOIN {yass_syncstore_state} state ON (
					state.replica_id = @yass_replicaId
					AND state.entity_type = "@entityType"
					AND state.entity_id = map.guid
				)
				WHERE state.u_replica_id IS NULL
			', $args));
			while ($row = db_fetch_object($q)) {
				$this->onUpdateEntity($row->guid);
			}
		}
	}

}
<?php

require_once 'YASS/DataStore.php';
require_once 'YASS/Replica.php';
require_once 'YASS/SyncStore/GenericSQL.php';

class YASS_SyncStore_CiviCRM extends YASS_SyncStore_GenericSQL {

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
	function onCreateSqlProcedures(YASS_Replica $replica) {
		$result = array();
		// foreach ($this->replica->schema->getEntityTypes() as $entityType) {
		// foreach (array('civicrm_activity') as $entityType) {
		// Note: We need stored-procs for all tables, regardless of whether they are syncable
		foreach ($this->replica->schema->getAllEntityTypes() as $entityType) {
			$result += $this->_createSqlProcedure($replica, $entityType);
		}
		return $result;
	}
	
	/**
	 * Generate a store procedure to update sync-states in a cascading manner which parallels SQL's cascade during deletion.
	 */
	protected function _createSqlProcedure(YASS_Replica $replica, $table) {
		$fks = $replica->schema->getIncomingForeignKeys($table);
		$staticArgs = array(
			'@yass_replicaId' => $replica->id,
			'@yass_effectiveReplicaId' => $replica->getEffectiveId(),
			'@entityType' => $table,
			'@entityIdColumn' => 'id',
		);
		$proc = array(
			'declare' => array(),
			'cmds' => array(),
		);
		
		if (!empty($fks)) {
			foreach ($fks as $id => $fk) {
				$fks[$id]['vars'] = array(
					'@fkFromTable' => $fk['fromTable'],
					'@fkFromCol' => $fk['fromCol'],
					'@fkToTable' => $fk['toTable'],
					'@fkToCol' => $fk['toCol'],
					'@fkOnDelete' => $fk['onDelete'],
					'@fkCursor' => 'cur_' . $fk['fromTable'] . '__' . $fk['fromCol'],
					'@fkLoop' => 'loop_' . $fk['fromTable'] . '__' . $fk['fromCol'],
				);
				
				// build list of tasks that need to be done to the related entity
				
				$fks[$id]['cmds'] = array(); // array(sqlString)
				if (in_array($fk['fromTable'], $this->replica->schema->getEntityTypes())) {
					$fks[$id]['cmds'][] = '
							UPDATE yass_syncstore_state state SET u_replica_id = @yass_effectiveReplicaId, u_tick = yass_thisTick
								WHERE state.replica_id = @yass_replicaId AND state.entity_id = relGuid;
					';
				}
				// CASCADE means: "if @toTable is deleted, then delete @fromTable"
				// SET NULL means: "if @toTable is deleted, then set @fromTable.@fromCol to null"
				if ($fk['onDelete'] == 'CASCADE') {
					$fks[$id]['cmds'][] = '
							CALL yass_cscd_@fkFromTable(relId, yass_thisTick);
					';
				}
			}
			
			$proc['declare'][] = 'DECLARE relId INT;';
			$proc['declare'][] = 'DECLARE relGuid VARCHAR(36);';
			$proc['declare'][] = 'DECLARE done INT DEFAULT FALSE;';
			foreach ($fks as $fk) {
				if (empty($fk['cmds'])) {
					$proc['declare'][] = strtr('-- UNNECESSARY: DECLARE @fkCursor CURSOR FOR...', $fk['vars']);
				} else {
					$proc['declare'][] = strtr('DECLARE @fkCursor CURSOR FOR
						SELECT relEntity.id, map.guid
						FROM @fkFromTable relEntity
						LEFT JOIN yass_guidmap map ON (map.entity_type="@fkFromTable" AND map.lid = relEntity.id)
						WHERE relEntity.@fkFromCol = deleteId;
					', $fk['vars']);
				}
			}
			$proc['declare'][] = 'DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;';
			foreach ($fks as $fk) {
				if (empty($fk['cmds'])) {
					$proc['cmds'][] = strtr('
						-- IGNORE: relation (@fkToTable.@fkToCol <=> @fkFromTable.@fkFromCol ; onDelete=@fkOnDelete) has no cascading commands
					', $fk['vars']);
				} else {
					$proc['cmds'][] = strtr('
						OPEN @fkCursor;
						SET done = FALSE;
						@fkLoop: LOOP
							FETCH @fkCursor INTO relId, relGuid;
							IF done THEN
								LEAVE @fkLoop;
							END IF;
							'.implode('', $fk['cmds']).'
						END LOOP;
						CLOSE @fkCursor;
					', $fk['vars']);
				}
			}
		}
		$result = array();
		$result['yass_cscd_' . $table] = array(
			'full_sql' => strtr(''
				. "CREATE PROCEDURE yass_cscd_@entityType (IN deleteId INT, IN yass_thisTick INT)\n"
				. "BEGIN\n"
				. implode("\n", $proc['declare'])
				. "\n"
				. implode("\n", $proc['cmds'])
				. "END;\n",
			$staticArgs),
		);
		return $result;
	}

	/**
	 * Implementation of hook_arms_trigger
	 */
	function onCreateSqlTriggers(YASS_Replica $replica) {
		// make sure that there's a last-seen value for the trigger to read
		db_query('INSERT IGNORE INTO {yass_syncstore_seen} (replica_id,r_replica_id,r_tick) VALUES (%d,%d,%d)',
			$replica->id, $replica->id, 0);
	
		$template = '
			IF @yass_disableTrigger IS NULL OR @yass_disableTrigger = 0 THEN
				SET yass_nextTick = 1+(SELECT max(r_tick) FROM yass_syncstore_seen
					WHERE replica_id = @yass_replicaId AND r_replica_id = @yass_effectiveReplicaId LIMIT 1);
				UPDATE yass_syncstore_seen SET r_tick = yass_nextTick
					WHERE replica_id = @yass_replicaId AND r_replica_id = @yass_effectiveReplicaId;
				SET yass_guid = (SELECT guid FROM yass_guidmap
					WHERE replica_id = @yass_replicaId AND entity_type = "@entityType" AND lid = {ACTIVE}.@entityIdColumn);
				IF yass_guid IS NULL OR yass_guid = "" THEN
					SET yass_guid = uuid();
					INSERT DELAYED INTO {yass_guidmap} (replica_id,entity_type,lid,guid)
						VALUES (@yass_replicaId, "@entityType", {ACTIVE}.@entityIdColumn, yass_guid);
				END IF;

				INSERT DELAYED INTO yass_syncstore_state (replica_id, entity_id, u_replica_id, u_tick, c_replica_id, c_tick) 
				VALUES (@yass_replicaId, yass_guid, @yass_effectiveReplicaId, yass_nextTick, @yass_effectiveReplicaId, yass_nextTick)
				ON DUPLICATE KEY UPDATE u_replica_id = @yass_effectiveReplicaId, u_tick = yass_nextTick;
			END IF
		';
		$beforeDelTemplate = '
			IF @yass_disableTrigger IS NULL OR @yass_disableTrigger = 0 THEN
				SET yass_nextTick = 1+(SELECT max(r_tick) FROM yass_syncstore_seen
					WHERE replica_id = @yass_replicaId AND r_replica_id = @yass_effectiveReplicaId LIMIT 1);
				UPDATE yass_syncstore_seen SET r_tick = yass_nextTick
					WHERE replica_id = @yass_replicaId AND r_replica_id = @yass_effectiveReplicaId;
				SET max_sp_recursion_depth = 10;
				CALL yass_cscd_@entityType(OLD.@entityIdColumn, yass_nextTick);
			END IF
		';
		
		// Only need on-insert and on-update triggers for syncable entities
		foreach ($this->replica->schema->getEntityTypes() as $table) {
			$staticArgs = array(
				'@yass_replicaId' => $replica->id,
				'@yass_effectiveReplicaId' => $replica->getEffectiveId(),
				'@entityType' => $table,
				'@entityIdColumn' => 'id',
			);
			$result[] = array(
				'table' => array($table),
				'when' => array('after'),
				'event' => array('insert', 'update','delete'),
				'declare' => array('yass_nextTick' => 'NUMERIC', 'yass_guid' => 'VARCHAR(36)'),
				'sql' => strtr($template, $staticArgs),
			);
		}
		
		// Need on-delete triggers for all entities, regardless of whether they are syncable, b/c cascades may affect syncable entities
		foreach ($this->replica->schema->getAllEntityTypes() as $table) {
			$staticArgs = array(
				'@yass_replicaId' => $replica->id,
				'@yass_effectiveReplicaId' => $replica->getEffectiveId(),
				'@entityType' => $table,
				'@entityIdColumn' => 'id',
			);
			$result[] = array(
				'table' => array($table),
				'when' => array('before'),
				'event' => array('delete'),
				'declare' => array('yass_nextTick' => 'NUMERIC'),
				'sql' => strtr($beforeDelTemplate, $staticArgs),
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
		foreach ($this->replica->schema->getEntityTypes() as $type) {
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
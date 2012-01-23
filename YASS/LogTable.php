<?php

class YASS_LogTable {
    const DEFAULT_MAX = 5000;
    
    /**
     * Log a batch of data transfers
     *
     * @param $entities array(YASS_Entity)
     * @param $entityVersions array(entityGuid => YASS_Version)
     */
    static function addAll(YASS_Replica $from, YASS_Replica $to, $entities, $entityVersions) {
        foreach ($entities as $entity) {
            self::add($from, $to, $entity, $entityVersions[$entity->entityGuid]);
        }
    }
    
    static function add(YASS_Replica $from, YASS_Replica $to, YASS_Entity $entity, YASS_Version $version) {
        $archive = array(
            'from_replica_id' => $from->id,
            'from_replica_name' => $from->name,
            'to_replica_id' => $to->id,
            'to_replica_name' => $to->name,
            'entity_type' => $entity->entityType,
            'entity_id' => $entity->entityGuid, 
            'u_replica_id' => $version->replicaId,
            'u_tick' => $version->tick,
            'timestamp' => arms_util_time(),
        );
        drupal_write_record('yass_log', $archive);
    }

    /**
     * Delete old log records
     *
     * @param $limit int the maximum number of log records that should be stored
     */
    static function prune($limit = FALSE) {
        if ($limit === FALSE) {
            $limit = variable_get('yass_logtable_max', self::DEFAULT_MAX);
        }
        // note: id is auto-increment
        $lastId = db_result(db_query('SELECT max(id) FROM {yass_log}'));
        if ($lastId > $limit) {
          db_query('DELETE FROM {yass_log} WHERE id <= %d', $lastId-$limit);
        }
    }
    
    /**
     * @param $limit int
     * @param $offset int
     * @return array({yass_log})
     */
    static function findRecent($limit, $offset = 0) {
        $q = db_query('SELECT * FROM {yass_log} ORDER BY id DESC LIMIT %d OFFSET %d', $limit, $offset);
        $result = array();
        while ($row = db_fetch_array($q)) {
            $result[] = $row;
        }
        return $result;
    }
}
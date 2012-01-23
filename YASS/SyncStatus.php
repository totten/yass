<?php
require_once 'YASS/Replica.php';

class YASS_SyncStatus {
    static function onStart(YASS_Replica $src, YASS_Replica $dest) {
        arms_util_include_api('query');
        $insert = arms_util_insert('yass_syncstatus', 'update')
            ->addValue('src_replica_id', $src->id, 'insert-only')
            ->addValue('dest_replica_id', $dest->id, 'insert-only')
            ->addValue('start_ts', arms_util_time(), 'insert-update')
            ->addValue('end_ts', NULL, 'insert-update');
        db_query($insert->toSQL());
    }
    
    static function onEnd(YASS_Replica $src, YASS_Replica $dest) {
        arms_util_include_api('query');
        $insert = arms_util_insert('yass_syncstatus', 'update')
            ->addValue('src_replica_id', $src->id, 'insert-only')
            ->addValue('dest_replica_id', $dest->id, 'insert-only')
            ->addValue('end_ts', arms_util_time(), 'insert-update');
        db_query($insert->toSQL());
    }
    
    /**
     * Get the list of sync statuses
     *
     * @param $srcReplicas array(YASS_Replica)
     * @param $destReplicas array(YASS_Replica)
     * @return array(srcReplicaId =>array(destReplicaId => {yass_syncstatus}))
     */
    static function find($srcReplicas, $destReplicas) {
        arms_util_include_api('array', 'query');
        $select = arms_util_query('{yass_syncstatus}')
            ->addSelects(array('src_replica_id', 'dest_replica_id', 'start_ts', 'end_ts'))
            ->addWhere(arms_util_query_in('src_replica_id', arms_util_array_collect($srcReplicas, 'id')))
            ->addWhere(arms_util_query_in('dest_replica_id', arms_util_array_collect($destReplicas, 'id')));
        $result = array();
        $q = db_query($select->toSQL());
        while ($row = db_fetch_array($q)) {
            $result[ $row['src_replica_id'] ][ $row['dest_replica_id'] ] = $row;
        }
        return $result;
    }
}

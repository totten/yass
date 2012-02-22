<?php

/*
 +--------------------------------------------------------------------+
 | YASS                                                               |
 +--------------------------------------------------------------------+
 | Copyright ARMS Software LLC (c) 2011-2012                          |
 +--------------------------------------------------------------------+
 | This file is a part of YASS.                                       |
 |                                                                    |
 | YASS is free software; you can copy, modify, and distribute it     |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007.                                       |
 |                                                                    |
 | YASS is distributed in the hope that it will be useful, but        |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | Additional permissions may be granted. See LICENSE.txt for         |
 | details.                                                           |
 +--------------------------------------------------------------------+
*/

require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/ConflictResolver.php';
require_once 'YASS/Replica.php';

/**
 * @public
 */
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
    
    private $_log;
    
    function __construct() {
        require_once 'YASS/Log.php';
        $this->_log = YASS_Log::instance('YASS_Engine');
    }
    
    /**
     * Register and instantiate a new replica
     */
    function createReplica($replicaSpec) {
        $this->getReplicas(); // cache
        $replicaSpec = $this->updateReplicaSpec($replicaSpec);
        $this->_replicas[$replicaSpec['id']] = YASS_Replica::create($replicaSpec);
        if ($replicaSpec['is_triggered']) {
            arms_util_include_api('procedure');
            arms_util_procedure_rebuild();
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
            $this->_replicas[$replicaSpec['id']] = YASS_Replica::create($replicaSpec);
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
        $this->_log->debug(array('updateReplicaSpec', $replicaSpec));
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
        $this->_log->info(array('destroyReplicas'));
        
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
        $this->_log->info(sprintf('destroyReplica name="%s" id="%s" effId="%s"', $replica->name, $replica->id, $replica->getEffectiveId()));
        
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
        $this->_checkReplicas("Cannot sync", $src, $dest);
        require_once 'YASS/SyncStatus.php';
        
        $this->_log->info(sprintf('bidir src="%s" dest="%s"', $src->name, $dest->name));
        
        YASS_SyncStatus::onStart($src, $dest); // FIXME loosen coupling -- move to separate listener
        module_invoke_all('yass_replica', array('op' => 'preSync', 'replica' => &$src));
        module_invoke_all('yass_replica', array('op' => 'preSync', 'replica' => &$dest));

        require_once 'YASS/Addendum.php';
        require_once 'YASS/Context.php';
        require_once 'YASS/Algorithm/Bidir.php';
        arms_util_include_api('array');

        $ctx = new YASS_Context(array(
            'action' => 'bidir',
            'addendum' => new YASS_Addendum(),
            'pairing' => new YASS_Pairing($src, $dest)
        ));
        
        $count = 0;
        while ($count < 1 || ($count < YASS_Addendum::MAX_ITERATIONS && YASS_Context::get('addendum')->isSyncRequired())) {
            YASS_Context::get('addendum')->clear();
            $job = new YASS_Algorithm_Bidir();
            $job->run($src, $dest, $conflictResolver);
            YASS_Context::get('addendum')->apply();
            $count ++;
        }
        
        module_invoke_all('yass_replica', array('op' => 'postSync', 'replica' => &$src));
        module_invoke_all('yass_replica', array('op' => 'postSync', 'replica' => &$dest));
        YASS_SyncStatus::onEnd($src, $dest); // FIXME loosen coupling -- move to separate listener
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
        $this->_checkReplicas("Cannot transfer", $src, $dest);
        if (empty($syncStates)) { return; }
        
        $entityVersions = arms_util_array_combine_properties($syncStates, 'entityGuid', 'modified');
        $entities = $src->data->getEntities(arms_util_array_collect($syncStates, 'entityGuid'));
        $this->transferData($src, $dest, $entities, $entityVersions);
    }
    
    /**
     * Transfer a set of records from one replica to another
     *
     * @param $entities array(entityGuid => YASS_Entity)
     * @param $entityVersions array(entityGuid => YASS_Version)
     */
    function transferData(
        YASS_Replica $src,
        YASS_Replica $dest,
        $entities,
        $entityVersions)
    {
//        print_r(array('transferData', $src->name, $dest->name, $entities));
        $this->_checkReplicas("Cannot transfer", $src, $dest);
        if (empty($entities)) { return; }
        
        // Although datastores and filters generally shouldn't use syncstate, there are exceptions.
        list ($usec, $sec) = explode(" ", microtime());
        $ctx = new YASS_Context(array(
            'action' => 'transfer',
            // 'syncStates' => $syncStates,
            'entityVersions' => $entityVersions,
            'transferId' => $src->id . '=>' . $dest->id . '~' . round($usec*1000000),
        ));
        
        if ($src->spec['is_logged'] || $dest->spec['is_logged']) {
            require_once 'YASS/LogTable.php';
            YASS_LogTable::addAll($src, $dest, $entities, $entityVersions);
        }
        
        $dest->data->putEntities($entities);
        $dest->sync->setSyncStates($entityVersions);
    }
    
    /**
     * Transfer a set of records from a replica's archive
     *
     * @param $entityVersions array(entityGuid=>YASS_Version) list of entities which should be restored, and the version of data to use
     */
    function restore(YASS_Replica $replica, $entityVersions) {
        if (empty($entityVersions)) return;
        $this->_log->info(sprintf('restore name="%s" id="%s" guids="%s"', $replica->name, $replica->id));
        $this->_log->debug($entityVersions);
        
        require_once 'YASS/Archive.php';
        $archive = new YASS_Archive($replica);
        
        $newEntities = array(); // array(entityGuid => YASS_Entity)
        $newEntityVersions = array(); // array(entityGuid => YASS_Version)
        
        $newVersion = $replica->sync->tick();
        foreach ($entityVersions as $entityGuid => $restoreVersion) {
            $newEntities[$entityGuid] = $archive->getEntity($entityGuid, $restoreVersion);
            if (!$newEntities[$entityGuid]) {
                throw new Exception(sprintf('Failed to locate %s@%d:%d in archive (replica=%s)', $entityGuid, $restoreVersion->replicaId, $restoreVersion->tick, $replica->name));
            }
            $newEntityVersions[$entityGuid] = $newVersion;
        }
        
        $ctx = new YASS_Context(array(
            'action' => 'restore',
            'entityVersions' => $newEntityVersions,
        ));
        
        $replica->data->putEntities($newEntities);
        $replica->sync->setSyncStates($newEntityVersions);
    }
    
    /**
     * Lazily, safely set the effective-replica-ID.
     *
     * If the effective-replica-ID has already been set correctly, this is a nullop
     */
    function setEffectiveReplicaId(YASS_Replica $replica, $effectiveReplicaId) {
        if ($replica->getEffectiveId() != $effectiveReplicaId) {
            $this->_log->info(sprintf('setEffectiveReplicaId name="%s" id="%s" effId="%s"', $replica->name, $replica->id, $effectiveReplicaId));
            $this->updateReplicaSpec(array(
                'name' => $replica->name,
                'effective_replica_id' => $effectiveReplicaId,
            ));
            $replica->spec['effective_replica_id'] = $effectiveReplicaId;
            $replica->effectiveId = $effectiveReplicaId;
            
            if ($replica->spec['is_triggered']) {
                arms_util_include_api('procedure');
                arms_util_procedure_rebuild();
                arms_util_include_api('trigger');
                arms_util_trigger_rebuild();
            }
        }
        /*
        if (!variable_get('yass_is_syncstate_migrated', FALSE)) {
            // UGGH. When a new, local runtime is brought online, it assigns its own (real+effective) IDs
            // and uses those to generate syncstates. However, once an effective ID is set, the
            // the local runtime abdicates responsibility for ID changes -- those will be coordinated
            // by the master. 
            // This approach is rather brittle -- for example, it becomes hard to understand what happens
            // in complex sync topologies. 
            module_invoke_all('yass_replica', array('op' => 'migrateSyncstate', 'replica' => &$replica, 'realId' => $replica->id, 'effectiveId' => $effectiveId));
            variable_set('yass_is_syncstate_migrated', TRUE);
        }
        */
    }
    
    protected function _changeReplicaId(YASS_Replica $replica) {
        $newSpec = $replica->spec;
        unset($newSpec['id']);
        arms_util_xt_save('yass_replicas', $newSpec);
        
        $oldId = $replica->id;
        $newId = $newSpec['id'];
        
        $this->_log->info(sprintf('changeReplicaId name="%s" oldId="%s" newId="%s"', $replica->name, $oldId, $newId));
        
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
        $this->_checkReplicas("Cannot join", $replica, $master);
        $this->_log->info(sprintf('join replica="%s" (%d) master="%s" (%d)', $replica->name, $replica->id, $master->name, $master->id));
        module_invoke_all('yass_replica', array('op' => 'preJoin', 'replica' => &$replica, 'master' => &$master));
        
        // teardown
        // if ($replica->spec['is_joined']) { // optimization: skip step for unjoined DBs; the existing syncstates are good enough
            // Note: optimization is defunct; when using proxies, the underlying syncstore should regenerate its
            // syncstate using the "effectiveReplicaId" rather than the locally-generated real ID.
            // Force replica and master to mutually resend all records by changing the replica ID.
            $replica->sync->destroy();
            $replica->mapper->destroy();
            $this->_changeReplicaId($replica);
        // }
        
        // buildup
        module_invoke_all('yass_replica', array('op' => 'validateGuids', 'replica' => &$replica));
        require_once 'YASS/ConflictResolver/Exception.php';
        require_once 'YASS/Algorithm/HardPush.php';
        $push = new YASS_Algorithm_HardPush();
        $push->run($replica, $master);
        $push->run($master, $replica);

        $replica->spec = $this->updateReplicaSpec(array(
            'name' => $replica->name, 'is_active' => TRUE, 'is_joined' => TRUE,
        ));
        
        module_invoke_all('yass_replica', array('op' => 'postJoin', 'replica' => &$replica, 'master' => &$master));
    }
    
    /**
     * Submit all data from $src to $dest, overwriting discrepancies in the $dest. Relies on existing ID-GUID mappings.
     */
    function hardPush(YASS_Replica $src, YASS_Replica $dest) {
        $this->_checkReplicas("Cannot hardPush", $src, $dest);
        $this->_log->info(sprintf('hardPush src="%s" (%d) dest="%s" (%d)', $src->name, $src->id, $dest->name, $dest->id));
        module_invoke_all('yass_replica', array('op' => 'preHardPush', 'replica' => &$dest, 'src' => &$src));
        
        // buildup
        module_invoke_all('yass_replica', array('op' => 'validateGuids', 'replica' => &$src));
        module_invoke_all('yass_replica', array('op' => 'validateGuids', 'replica' => &$dest));
        
        require_once 'YASS/ConflictResolver/Exception.php';
        require_once 'YASS/Algorithm/HardPush.php';
        $push = new YASS_Algorithm_HardPush();
        $push->run($src, $dest);
        $dest->spec = $this->updateReplicaSpec(array(
            'name' => $dest->name, 'is_active' => TRUE, 'is_joined' => TRUE,
        ));
        
        module_invoke_all('yass_replica', array('op' => 'postHardPush', 'replica' => &$dest, 'src' => &$src));
    }
    
    /**
     * Increment the tick of all entities in the replica, forcing them to propagate
     */
    function hardTick(YASS_Replica $replica) {
        $this->_checkReplicas("Cannot hardTick", $replica, $replica);
        $this->_log->info(sprintf('hardPush replica="%s" (%d)', $replica->name, $replica->id));
        
        module_invoke_all('yass_replica', array('op' => 'preHardTick', 'replica' => &$replica));
        module_invoke_all('yass_replica', array('op' => 'validateGuids', 'replica' => &$replica));
        $replica->sync->updateAllVersions();
        module_invoke_all('yass_replica', array('op' => 'postHardTick', 'replica' => &$replica));
    }
    
    /**
     * Synchronize all replicas with a master
     *
     * FIXME: DRY: yass_ui.console.inc duplicates this process in a manner that works in Drupal's Batch API
     */
    function syncAll(YASS_Replica $master, YASS_ConflictResolver $conflictResolver) {
        $this->_log->info(sprintf('syncAll master="%s" (%d)', $master->name, $master->id));
        $this->_checkReplicas("Cannot syncAll", $master, $master);
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
            // mitigate risk of concurrency issues when adding new entities by using the $master; the master is generally only manipulated by one thread
            $this->bidir($replica, $master, $conflictResolver);
        }
    }
    
    /**
     * Ensure that the given items are replicas
     */
    protected function _checkReplicas($message, $src, $dest) {
        if (!is_object($src) || !$src->id || !$src->name) {
            throw new Exception($message . ": Missing first replica");
        }
        if (!is_object($dest) || !$dest->id || !$dest->name) {
            throw new Exception($message . ": Missing second replica");
        }
    }
    
    function createGuid() {
        $domain = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_'; // 64=2^6 => 6 bits per character
        
        // n = sqrt(2 * d * ln(1/1-p))
        // d=2^(charbit*len)
        // n = sqrt(2 * 2^(charbit*len) * ln(1/1-p))
        // n = 2^(charbit*len/2) * sqrt(2 * ln(1/1-p))
        
        // charbit=6, len=16, p=0.01  : 2^48 * sqrt(2*ln(1/0.99))   = 39.91e+12 (40 trillion records -- 1% chance of collision)
        // charbit=6, len=16, p=0.001 : 2^48 * sqrt(2*ln(1/0.999))  = 12.59e+12 (13 trillion records -- 0.1% chance of collision)
        // charbit=6, len=16, p=0.0001: 2^48 * sqrt(2*ln(1/0.9999)) = 3.981e+12 (4 trillion records -- 0.01% chance of collision)
        
        // charbit=6, len=20, p=0.01  : 2^60 * sqrt(2*ln(1/0.99))   = 163.5e+15 (163 quadrillion records -- 1% chance of collision)
        // charbit=6, len=20, p=0.001 : 2^60 * sqrt(2*ln(1/0.999))  = 51.57e+15 (51 quadrillion records -- 0.1% chance of collision)
        // charbit=6, len=20, p=0.0001: 2^60 * sqrt(2*ln(1/0.9999)) = 16.31e+15 (16 quadrillion records -- 0.01% chance of collision)
        
        // charbit=6, len=32, p=0.01  : 2^96 * sqrt(2*ln(1/0.99))   = 11.23e+27 (11 octillion records -- 1% chance of collision)
        // charbit=6, len=32, p=0.001 : 2^96 * sqrt(2*ln(1/0.999))  = 3.544e+27 (4 octillion records -- 0.1% chance of collision)
        // charbit=6, len=32, p=0.0001: 2^96 * sqrt(2*ln(1/0.9999)) = 1.120e+27 (1 octillion records -- 0.01% chance of collision)
        
        $result = '';
        for ($i = 0; $i < 20; $i++) {
            $r = rand(0, strlen($domain) - 1);
            $result = $result . $domain{$r};
        }
        return $result;
    }
}

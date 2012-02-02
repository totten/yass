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
require_once 'YASS/Replica.php';
require_once 'YASS/SyncStore.php';

class YASS_SyncStore_GenericSQL extends YASS_SyncStore {

    /**
     * @var array(replicaId => YASS_Version)
     */
    var $lastSeen;
    
    /**
     * @var bool
     */
    var $disableCache;
    
    /**
     * 
     */
    public function __construct(YASS_Replica $replica) {
        arms_util_include_api('query');
        $this->replica = $replica;
        $lastSeen = $this->getLastSeenVersions();
        if (! $lastSeen[$this->replica->getEffectiveId()]) {
            $this->markSeen(new YASS_Version($this->replica->getEffectiveId(), 0));
        }
    }

    /**
     * Find a list of revisions that have been previously applied to a replica
     *
     * @return array(replicaId => YASS_Version)
     */
    function getLastSeenVersions() {
        if ($this->disableCache || !is_array($this->lastSeen)) {
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
    protected function markSeen(YASS_Version $lastSeen) {
        $lastSeens = $this->getLastSeenVersions(); // fill cache
        if (!$lastSeens[$lastSeen->replicaId]
            || $lastSeen->tick > $lastSeens[$lastSeen->replicaId]->tick 
        ) {
            db_query('INSERT INTO {yass_syncstore_seen} (replica_id, r_replica_id, r_tick) 
                VALUES (%d, %d, %d)
                ON DUPLICATE KEY UPDATE r_tick = IF(%d>r_tick,%d,r_tick)
            ', $this->replica->id, $lastSeen->replicaId, $lastSeen->tick, $lastSeen->tick, $lastSeen->tick);
            $this->lastSeen[ $lastSeen->replicaId ] = $lastSeen;
        }
        return $lastSeen;
    }
    
    /**
     * Find all records in a replica which have been modified since the given point
     *
     * @return array(entityGuid => YASS_SyncState)
     */
    protected function getModified(YASS_Version $lastSeen = NULL) {
        $select = arms_util_query('{yass_syncstore_state} state');
        $select->addSelects(array('state.replica_id', 'state.entity_id', 'state.u_replica_id', 'state.u_tick', 'state.c_replica_id', 'state.c_tick'));
        $select->addWheref('state.replica_id = %d', $this->replica->id);
        if (!$lastSeen) {
            $select->addwheref('state.u_replica_id = %d', $this->replica->getEffectiveId());
        } else {
            $select->addwheref('state.u_replica_id = %d', $lastSeen->replicaId);
            $select->addWheref('state.u_tick > %d', $lastSeen->tick);
        }
        if ($this->replica->accessControl) {
            $pairing = YASS_Context::get('pairing');
            if (!$pairing) {
                throw new Exception('Failed to locate active replica pairing');
            }
            $partnerReplica = $pairing->getPartner($this->replica->id);
            if (!$partnerReplica) {
                throw new Exception('Failed to locate partner replica');
            }
            $select->addJoinf('INNER JOIN {yass_ace} ace 
                ON ace.replica_id = state.replica_id 
                AND ace.guid = state.entity_id
                AND ace.client_replica_id=%d',
                $partnerReplica->id);
            // Return syncstate even if is_allowed=0 -- when an entity
            // is changed in a way that affects visibility, we still
            // need to share syncstate (even if we can no longer share
            // the data).
        }
        
        if ($threshold = YASS_Context::get('abortThreshold')) {
            $count = db_result(db_query($select->toCountSQL()));
            // arms_util_log_dbg(array('check threshold', 'replica' => $this->replica->name, 'version' => $lastSeen, 'threshold' => $threshold, 'count' => $count, 'sql' => $select->toCountSQL()));
            if ($count > $threshold) {
                throw new Exception(sprintf('Too many records to synchronize from replica (%s) -- count (%d) exceeds limit (%d)', $this->replica->name, $count, $threshold));
            }
        }
        
        $q = db_query($select->toSQL());
        $modified = array();
        while ($row = db_fetch_object($q)) {
            $modified[ $row->entity_id ] = $this->toYassSyncState($row);
        }
        return $modified;
    }
    
    /**
     * Obtain the next available version number
     *
     * @return YASS_Version
     */
    function tick() {
        // update tick count
        $lastSeens = $this->getLastSeenVersions(); // fill cache
        if ($lastSeens[$this->replica->getEffectiveId()]) {
            $lastSeens[$this->replica->getEffectiveId()] = $this->markSeen($lastSeens[$this->replica->getEffectiveId()]->next());
        } else {
            $lastSeens[$this->replica->getEffectiveId()] = $this->markSeen(new YASS_Version($this->replica->getEffectiveId(), 1));
        }
        return $lastSeens[$this->replica->getEffectiveId()];
    }
    
    /**
     *
     */
    function onUpdateEntity($entityGuid) {
        $this->setSyncState($entityGuid, $this->tick());
    }
    
    /**
     * Determine the sync state of a particular entity
     *
     * @return YASS_SyncState
     */
    protected function getSyncState($entityGuid) {
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
    protected function setSyncState($entityGuid, YASS_Version $modified) {
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
     * Forcibly increment the versions of entities to make the current replica appear newest
     */
    function updateAllVersions() {
        // FIXME inefficient
        $q = db_query('SELECT entity_id FROM {yass_syncstore_state} WHERE replica_id=%d', $this->replica->id);
        while ($entityGuid = db_result($q)) {
            $this->onUpdateEntity($entityGuid);
        }
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

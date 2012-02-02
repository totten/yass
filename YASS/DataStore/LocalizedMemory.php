<?php

require_once 'YASS/Engine.php';
require_once 'YASS/DataStore/Local.php';
require_once 'YASS/Replica.php';

/**
 * An an in-memory data store for which entities are stored with localized IDs
 */
class YASS_DataStore_LocalizedMemory extends YASS_DataStore_Local {

    /**
     * 
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     */
    public function __construct(YASS_Replica $replica) {
        require_once 'YASS/LocalDataStore/Memory.php';
        parent::__construct($replica, new YASS_LocalDataStore_Memory());
    }
    
    /**
     * Get a list of all entities
     *
     * This is an optional interface to facilitate testing/debugging
     *
     * FIXME This override should be unnecessary
     *
     * @return array(entityGuid => YASS_Entity)
     */
    function getAllEntitiesDebug() {
        $result = array();
        foreach (array_keys($this->localDataStore->entities) as $type) {
            $result = $result + $this->localDataStore->getAllEntitiesDebug($type, $this->replica->mapper);
        }
        return $result;
    }

    /**
     * Put content directly in the data store, bypassing the synchronization system.
     * This creates an un-synchronized entity.
     *
     * @return int, local id
     * @deprecated
     */
    function nativePut($type, $data, $lid = FALSE) {
        if (!$lid) {
            return $this->localDataStore->insert($type, $data);
        } else {
            $this->localDataStore->insertUpdate($type, $lid, $data);
            return $lid;
        }
    }
    
    function onPreSync(YASS_Replica $replica) {
        // This implementation does a bad job of maintaining GUID mappings, so
        // we need to do validation before every sync.
        $this->onValidateGuids($replica);
    }
    
    function onValidateGuids(YASS_Replica $replica) {
        $this->localDataStore->onValidateGuids($replica);
    }
    
}


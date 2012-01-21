<?php

require_once 'YASS/Engine.php';
require_once 'YASS/DataStore.php';
require_once 'YASS/Replica.php';

/**
 * An an in-memory data store for which entities are stored with localized IDs
 */
class YASS_DataStore_LocalizedMemory extends YASS_DataStore {
    /**
     * @var array(entityType => lid)
     */
    var $maxIds;
    
    /**
     * @var array(entityType => array(lid => data))
     */
    var $entities;

    /**
     * 
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     */
    public function __construct(YASS_Replica $replica) {
        arms_util_include_api('array');
        $this->replica = $replica;
        $this->entities = array();
        $this->maxIds = array();
    }
    
    /**
     * Get the content of an entity
     *
     * @return array(entityGuid => YASS_Entity)
     */
    function _getEntities($entityGuids) {
        $this->replica->mapper->loadGlobalIds($entityGuids);
        $result = array();
        foreach ($entityGuids as $entityGuid) {
            list ($type, $lid) = $this->replica->mapper->toLocal($entityGuid);
            if ($this->entities[$type][$lid]) {
                $result[$entityGuid] = new YASS_Entity($entityGuid, $type, $this->entities[$type][$lid]);
            }
        }
        return $result;
    }

    /**
     * Save an entity
     *
     * @param $entities array(YASS_Entity)
     */
    function _putEntities($entities) {
        $this->replica->mapper->loadGlobalIds(array_keys($entities));
        foreach ($entities as $entity) {
            list ($type, $lid) = $this->replica->mapper->toLocal($entity->entityGuid);
            if ($entity->exists) {
                if (! ($type && $lid)) {
                    $type = $entity->entityType;
                    if (!isset($this->maxIds[$entity->entityType])) {
                        $this->maxIds[$entity->entityType] = 0;
                    }
                    $lid = ++ $this->maxIds[$entity->entityType];
                    $this->replica->mapper->addMappings(array(
                        $type => array($lid => $entity->entityGuid)
                    ));
                }
                $this->entities[$type][$lid] = $entity->data;
            } else {
                if ($type && $lid) {
                    unset($this->entities[$type][$lid]);
                } // else: doesn't exist, no need to delete
            }
        }
    }
    
    /**
     * Get a list of all entities
     *
     * This is an optional interface to facilitate testing/debugging
     *
     * @return array(entityGuid => YASS_Entity)
     */
    function getAllEntitiesDebug() {
        $result = array();
        foreach ($this->entities as $type => $entities) {
            foreach ($entities as $lid => $entity) {
                $entityGuid = $this->replica->mapper->toGlobal($type, $lid);
                $result[$entityGuid] = new YASS_Entity($entityGuid, $type, $this->entities[$type][$lid]);
            }
        }
        return $result;
    }

    /**
     * Put content directly in the data store, bypassing the synchronization system.
     * This creates an un-synchronized entity.
     *
     * @return int, local id
     */
    function nativePut($type, $data, $lid = FALSE) {
        if (!$lid) {
            if (!isset($this->maxIds[$type])) {
                $this->maxIds[$type] = 0;
            }
            $lid = ++ $this->maxIds[$type];
        }
        $this->entities[$type][$lid] = $data;
        return $lid;
    }
    
    function onPreSync(YASS_Replica $replica) {
        // This implementation does a bad job of maintaining GUID mappings, so
        // we need to do validation before every sync.
        $this->onValidateGuids($replica);
    }
    
    function onValidateGuids(YASS_Replica $replica) {
        // create GUIDs for any unmapped entities
        foreach ($this->entities as $type => $entities) {
            foreach ($entities as $lid => $entity) {
                $entityGuid = $this->replica->mapper->toGlobal($type, $lid);
                // printf("onPreSync: %s [%s:%s]=>[%s]\n", $this->replica->name, $type, $lid, $entityGuid);
                if (empty($entityGuid)) {
                    $entityGuid = YASS_Engine::singleton()->createGuid();
                    $this->replica->mapper->addMappings(array(
                        $type => array($lid => $entityGuid)
                    ));
                    $this->replica->sync->onUpdateEntity($entityGuid);
                    // printf("onPreSync: %s [%s:%s]=>[%s] (generated)\n", $this->replica->name, $type, $lid, $entityGuid);
                }
            }
        }
    }
    
}


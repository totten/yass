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

require_once 'YASS/ILocalDataStore.php';
require_once 'YASS/Replica.php';

/**
 * This is a helper which bridges the gap between YASS's native convention (based on GUIDs)
 * and the common convention in which an entity is assigned a locally-unique ID.
 */
class YASS_DataStore_Local extends YASS_DataStore {

    /**
     * @var YASS_ILocalDataStore
     */
    var $localDataStore;

    /**
     * 
     */
    public function __construct(YASS_Replica $replica, YASS_ILocalDataStore $localDataStore) {
        arms_util_include_api('array');
        arms_util_include_api('query');
        $this->replica = $replica;
        $this->localDataStore = $localDataStore;
    }
    
    /**
     * Get the content of an entity
     *
     * @return array(entityGuid => YASS_Entity)
     */
    function _getEntities($entityGuids) {
        $this->replica->mapper->loadGlobalIds($entityGuids);
        
        $lidsByType = array(); // array(type => array(lid))
        foreach ($entityGuids as $entityGuid) {
            list ($type,$lid) = $this->replica->mapper->toLocal($entityGuid);
            if ($type && $lid) {
                $lidsByType[$type][$entityGuid] = $lid;
            } // else: omit; recall that YASS_DataStore::getEntities() creates tombstones for missing guids
        }
        
        $result = array(); // array(entityGuid => YASS_Entity)
        foreach ($lidsByType as $type => $lids) {
            $result = $result + $this->localDataStore->getEntities($type, $lids);
        }
        
        return $result;
    }

    /**
     * Save an entity
     *
     * The order of entity operations should be determined by:
     * (a) operation type -- inserts (exists==TRUE) before deletes (exists==FALSE)
     * (b) entity type (eg civicrm_phone has FK's to civicrm_contact)
     *
     * @param $entities array(YASS_Entity)
     */
    function putEntities($entities) {
        arms_util_include_api('array');
        $tableWeights = $this->localDataStore->getEntityWeights();
        asort($tableWeights);
        
        // To facilitate ordering of operations, index entities by (existence,type)
        $entitiesByExistsType = array(); // arms_util_array_index(array('exists','entityType','entityGuid'), $entities);
        foreach ($entities as $entity) {
            if ($entity->exists) {
                $entitiesByExistsType[TRUE][$entity->entityType][$entity->entityGuid] = $entity;
            } else {
                // For deleted entities, entityTypes aren't provided, so we have to look up entityTypes via GUID-mapper.
                // In bulk, this might be slow b/c we didn't warm the GUID-mapper's cache...
                // But bulk deletes that should be rare...
                list ($type, $lid) = $this->replica->mapper->toLocal($entity->entityGuid);
                if ($type && $lid) {
                    $entitiesByExistsType[FALSE][$type][$entity->entityGuid] = $entity;
                } // else: not in our datastore... don't care about it
            }
        }
        
        // New and updated entities
        if (is_array($entitiesByExistsType[TRUE])) {
            // Well-known tables, ascending
            foreach ($tableWeights as $entityType => $weight) {
                if (is_array($entitiesByExistsType[TRUE][$entityType])) {
                    parent::putEntities($entitiesByExistsType[TRUE][$entityType]);
                    unset($entitiesByExistsType[TRUE][$entityType]);
                }
            }
        
            // Unknown tables
            foreach ($entitiesByExistsType[TRUE] as $entityType => $someEntities) {
                parent::putEntities($someEntities);
            }
        }
        
        // Deleted entities
        if (is_array($entitiesByExistsType[FALSE])) {
            // Unknown tables
            foreach ($entitiesByExistsType[FALSE] as $entityType => $someEntities) {
                if (isset($tableWeights[$entityType])) continue;
                parent::putEntities($someEntities);
            }
        
            // Well-known-tables, descending
            foreach (array_reverse($tableWeights, TRUE) as $entityType => $weight) {
                if (is_array($entitiesByExistsType[FALSE][$entityType])) {
                    parent::putEntities($entitiesByExistsType[FALSE][$entityType]);
                    unset($entitiesByExistsType[FALSE][$entityType]);
                }
            }
        }
    }
    
    /**
     * Save an entity
     *
     * @param $entities array(YASS_Entity)
     */
    function _putEntities($entities) {
        $this->replica->mapper->loadGlobalIds(array_keys($entities));
        foreach ($entities as $entity) {
            if ($entity->exists && !in_array($entity->entityType, $this->localDataStore->getEntityTypes())) {
                printf("Error: Unsupported entity type [%s]\n", $entity->entityType);
                continue;
            }
            
            list ($type, $lid) = $this->replica->mapper->toLocal($entity->entityGuid);
            if ($entity->exists && ! ($type && $lid)) {
                $lid = $this->localDataStore->insert($entity->entityType, $entity->data);
                $this->replica->mapper->addMappings(array(
                    $entity->entityType => array($lid => $entity->entityGuid)
                ));
            } elseif ($entity->exists && ($type && $lid)) {
                $this->localDataStore->insertUpdate($type, $lid, $entity->data);
            } elseif (!$entity->exists && ! ($type && $lid)) {
                // nothing to do
            } elseif (!$entity->exists &&   ($type && $lid)) {
                $this->localDataStore->delete($type, $lid);
            } else {
                // should be impossible -- the above should exhaust all 2x2 combinations
                throw new Exception(sprintf('[%s] Failed to determine entity update (GUID=%s, type=%s, lid=%s, exists=%s).',
                    $this->replica->name, $entity->entityGuid,
                    $type, $lid, $entity->exists
                ));
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
        $result = array(); // array(entityGuid => YASS_Entity)
        foreach ($this->localDataStore->getEntityTypes() as $type) {
            $result = $result + $this->localDataStore->getAllEntitiesDebug($type, $this->replica->mapper);
        }
        return $result;
    }

}

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
 * Split up entity storage across different local data stores.
 */
class YASS_LocalDataStore_Hybrid implements YASS_ILocalDataStore {

    /**
     * @var array(entityType=>weight) list of entity types which should be stored in GenericSQL if not supported by CiviCRM
     */
    static $_FALLBACK_ENTITY_WEIGHTS = array(
        'civicrm_website' => 10,
    );

    /**
     * 
     */
    public function __construct(YASS_Replica $replica) {
        arms_util_include_api('array');
        arms_util_include_api('query');
        require_once 'YASS/LocalDataStore/CiviCRM.php';
        require_once 'YASS/LocalDataStore/GenericSQL.php';
        require_once 'YASS/LocalDataStore/YASS.php';
        $this->civicrm = new YASS_LocalDataStore_CiviCRM($replica, $replica->schema->schemas['civicrm']);
        $this->yass = new YASS_LocalDataStore_YASS($replica);
        
        // We use a fallback to ensure that this replica participates in data management (bidir, hardpush, etc)
        // for all entities; however, the fallback datastore should only be used by YASS -- not by application logic.
        $fallbackEntityTypes = array_diff(array_keys(self::$_FALLBACK_ENTITY_WEIGHTS), $this->civicrm->getEntityTypes());
        $this->fallback = new YASS_LocalDataStore_GenericSQL($replica, 
            arms_util_array_keyslice(self::$_FALLBACK_ENTITY_WEIGHTS, $fallbackEntityTypes));
    }
    
    /**
     *
     * @return array(entityType)
     */
    function getEntityTypes() {
        return array_merge(
            $this->fallback->getEntityTypes(),
            $this->civicrm->getEntityTypes(),
            $this->yass->getEntityTypes()
        );
    }

    /**
     * Detremine the order in which entities should be written to DB.
     *
     * Low-weight items are inserted before high-weight items.
     * High-weight items are deleted before low-weight items.
     *
     * @return array(entityType => weight)
     */
    function getEntityWeights() {
        return array_merge(
            $this->fallback->getEntityWeights(),
            $this->civicrm->getEntityWeights(),
            $this->yass->getEntityWeights()
        );
    }
    
    protected function pickLDS($type) {
        // if (substr($type, 0, 8) == 'civicrm_') {
        switch ($type) {
            case 'yass_conflict':
            case 'yass_mergelog':
                return $this->yass;
            default:
                // fallback should be the shorter list
                if (in_array($type, $this->fallback->getEntityTypes())) {
                    return $this->fallback;
                } else {
                    return $this->civicrm;
                }
        }
    }

    /**
     * Read a batch of entities
     *
     * @var $lids array(entityGuid => lid)
     * @return array(entityGuid => YASS_Entity)
     */
    function getEntities($type, $lids) {
        return $this->pickLDS($type)->getEntities($type, $lids);
    }
    
    /**
     * Add a new entity and generate a new local-id
     *
     * @return local id
     * @throws Exception
     */
    function insert($type, $data) {
        return $this->pickLDS($type)->insert($type, $data);
    }
    
    /**
     * Insert an entity using a specific local-id. If it already exists, then update it.
     *
     * @return void
     * @throws Exception
     */
    function insertUpdate($type, $lid, $data) {
        return $this->pickLDS($type)->insertUpdate($type, $lid, $data);
    }
    
    /**
     * Delete an entity
     */
    function delete($type, $lid) {
        return $this->pickLDS($type)->delete($type, $lid);
    }

    /**
     * Get a list of all entities
     *
     * This is an optional interface to facilitate testing/debugging
     *
     * @return array(entityGuid => YASS_Entity)
     */
    function getAllEntitiesDebug($type, YASS_IGuidMapper $mapper) {
        return $this->pickLDS($type)->getAllEntitiesDebug($type, $mapper);
    }

}

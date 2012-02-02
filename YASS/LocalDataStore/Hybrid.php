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

class YASS_LocalDataStore_Hybrid implements YASS_ILocalDataStore {

    /**
     * 
     */
    public function __construct(YASS_Replica $replica) {
        arms_util_include_api('array');
        arms_util_include_api('query');
        require_once 'YASS/LocalDataStore/CiviCRM.php';
        require_once 'YASS/LocalDataStore/Drupal.php';
        $this->civicrm = new YASS_LocalDataStore_CiviCRM($replica);
        $this->drupal = new YASS_LocalDataStore_Drupal();
    }
    
    /**
     *
     * @return array(entityType)
     */
    function getEntityTypes() {
        return array_merge(
            $this->civicrm->getEntityTypes(),
            $this->drupal->getEntityTypes()
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
            $this->civicrm->getEntityWeights(),
            $this->drupal->getEntityWeights()
        );
    }
    
    protected function pickLDS($type) {
        // if (substr($type, 0, 8) == 'civicrm_') {
        if ($type != 'yass_conflict') {
            return $this->civicrm;
        } else {
            return $this->drupal;
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

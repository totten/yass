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

/**
 * A fake datastore which prints incoming entities on the console
 */
class YASS_DataStore_Console extends YASS_DataStore {

    /**
     * 
     */
    public function __construct(YASS_Replica $replica) {
        arms_util_include_api('array');
        $this->replica = $replica;
        $this->prefix = 'data/';
    }
    
    /**
     * Get the content of an entity
     *
     * @return array(entityGuid => YASS_Entity)
     */
    function _getEntities($entityGuids) {
        throw new Exception('Not implemented: YASS_DataStore_Console::_getEntities()');
    }

    /**
     * Save an entity
     *
     * @param $entities array(YASS_Entity)
     */
    function _putEntities($entities) {
        sort($entities, arms_util_sort_by('entityGuid'));
        foreach ($entities as $entity) {
            $this->printEntity($entity);
        }
    }
    
    function printEntity(YASS_Entity $entity) {
        $arr = (array)$entity;
        $arr = arms_util_implode_tree('/', $arr, TRUE);
        ksort($arr);
        
        $guid = $arr['entityGuid'];
        unset($arr['entityGuid']);
        
        foreach ($arr as $key => $value) {
            if ($value === NULL) continue; //x
            if ($value === '') continue; //x
            //printf("%s%-60s \"%s\"\n", $this->prefix, $guid . '/' . $key, $value);
            printf("%s%s \"%s\"\n", $this->prefix, $guid . '/' . $key, $value);
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
        throw new Exception('Not implemented: YASS_DataStore_Console::getAllEntitiesDebug()');
    }
}

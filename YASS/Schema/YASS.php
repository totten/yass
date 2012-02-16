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

require_once 'YASS/ReplicaListener.php';
require_once 'YASS/ISchema.php';

class YASS_Schema_YASS extends YASS_ReplicaListener implements YASS_ISchema {
    static $instance;
    
    /**
     * Look up the schema
     */
    static function instance() {
        if (! isset(self::$instance)) {
            self::$instance = new YASS_Schema_YASS();
        }
        return self::$instance;
    }

    /**
     * Get a list of all entity types supported by the schema, regardless of whether they can be sync'd
     *
     * @return array(entityType)
     */
    function getAllEntityTypes() {
        return $this->getEntityTypes();
    }
    
    /**
     * Get a list of synchronizable entity types
     *
     * @return array(entityType)
     */
    function getEntityTypes() {
        return array('yass_conflict', 'yass_mergelog');
    }
    
    
    /**
     * Look up any related tables to which deletions should cascade
     *
     * @param $tableName string, the table in which an entity is deleted
     * @return array(array('fromTable' => $tableName, 'fromCol' => $columnName, 'toTable' => $tableName, 'toCol' => $columnName, 'onDelete' => $mode))
     *    list of of FKs which point to $tablename
     */
    function getIncomingForeignKeys($tableName) {
        return array();
    }
    
    /**
     * Get a set of local<->global filters for the given release of CiviCRM
     *
     * @return array(YASS_Filter)
     */
    function onBuildFilters(YASS_Replica $replica) {
        if (is_array($this->filters)) {
            return $this->filters;
        }
        
        require_once 'YASS/Filter/FK.php';
        require_once 'YASS/Filter/FlexFK.php';
        
        $this->filters = array();
        $this->filters[] = new YASS_Filter_FK(array(
            'entityType' => 'yass_conflict',
            'field' => 'contact_id',
            'fkType' => 'civicrm_contact',
            'onUnmatched' => 'exception', // 'skip',
        ));
        $this->filters[] = new YASS_Filter_FlexFK(array(
            'entityType' => 'yass_mergelog',
            'field' => 'kept_id',
            'fkTypeField' => 'entity_type',
            'onUnmatched' => 'skip',
        ));
        $this->filters[] = new YASS_Filter_FlexFK(array(
            'entityType' => 'yass_mergelog',
            'field' => 'destroyed_id',
            'fkTypeField' => 'entity_type',
            'onUnmatched' => 'skip',
        ));
        $this->filters[] = new YASS_Filter_FK(array(
            'entityType' => 'yass_mergelog',
            'field' => 'by_contact_id',
            'fkType' => 'civicrm_contact',
            'onUnmatched' => 'exception',
        ));
        
        return $this->filters;
    }
}

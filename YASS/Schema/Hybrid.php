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

require_once 'YASS/ISchema.php';

class YASS_Schema_Hybrid implements YASS_ISchema {

    /**
     * @var array(name => YASS_ISchema)
     */
    var $schemas;
    
    /**
     * @return array(name => YASS_ISchema)
     */
    function __construct($schemas) {
        $this->schemas = $schemas;
    }
    
    protected function _callAll($function) {
        $args = func_get_args();
        $function = array_shift($args);
        $result = array();
        foreach ($this->schemas as $schema) {
            $result = array_merge($result, call_user_func_array(array($schema, $function), $args));
        }
        return $result;
    }

    /**
     * Get a list of all entity types supported by the schema, regardless of whether they can be sync'd
     *
     * @return array(entityType)
     */
    function getAllEntityTypes() {
        if (! is_array($this->allEntityTypes)) {
            $this->allEntityTypes = $this->_callAll('getAllEntityTypes');
        }
        return $this->allEntityTypes;
    }
    
    /**
     * Get a list of synchronizable entity types
     *
     * @return array(entityType)
     */
    function getEntityTypes() {
        if (! is_array($this->entityTypes)) {
            $this->entityTypes = $this->_callAll('getEntityTypes');
        }
        return $this->entityTypes;
    }
    
    
    /**
     * Look up any related tables to which deletions should cascade
     *
     * @param $tableName string, the table in which an entity is deleted
     * @return array(array('fromTable' => $tableName, 'fromCol' => $columnName, 'toTable' => $tableName, 'toCol' => $columnName, 'onDelete' => $mode))
     *    list of of FKs which point to $tablename
     */
    function getIncomingForeignKeys($tableName) {
        if ($schema = $this->getSchemaByTable($tableName)) {
            return $schema->getIncomingForeignKeys($tableName);
        }
        return array();
    }
    
    /**
     * @return YASS_ISchema
     */
    protected function getSchemaByTable($tableName) {
        // This currently isn't used much, but if that changes it should be optimized
        foreach ($this->schemas as $schema) {
            if (in_array($tableName, $schema->getEntityTypes())) {
                return $schema;
            }
        }
        return NULL;
    }
}

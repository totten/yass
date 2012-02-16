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

interface YASS_ISchema {

    /**
     * Get a list of all entity types supported by the schema, regardless of whether they can be sync'd
     *
     * @return array(entityType)
     */
    function getAllEntityTypes();
    
    /**
     * Get a list of synchronizable entity types
     *
     * @return array(entityType)
     */
    function getEntityTypes();
    
    /**
     * Look up any related tables to which deletions should cascade
     *
     * @param $tableName string, the table in which an entity is deleted
     * @return array(array('fromTable' => $tableName, 'fromCol' => $columnName, 'toTable' => $tableName, 'toCol' => $columnName, 'onDelete' => $mode))
     *    list of of FKs which point to $tablename
     */
    function getIncomingForeignKeys($tableName);
}

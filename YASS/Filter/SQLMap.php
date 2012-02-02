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

require_once 'YASS/Filter/FieldValue.php';

/**
 * Convert field values by using a local SQL lookup table 
 *
 * Note: This uses the *local* SQL-backed mappings; this may constrain which replicas can/should use it
 */
class YASS_Filter_SQLMap extends YASS_Filter_FieldValue {

    /**
     * Cache SQL-map queries (e.g. "select id, iso_code from civicrm_country") because they may be redundant
     *
     * @var array(queryCacheKey => array(globalValue => localValue))
     */
    static $queryCache = array();

    /**
     *
     * @param $spec array; keys: 
     *  - entityType: string, the type of entity to which the filter applies
     *  - field: string, the incoming field name
     *  - sql: string, SELECT query which returns tuples with "local" and "global" columns
     */
    function __construct($spec) {
        $spec['queryCacheKey'] = md5($spec['sql']);
        parent::__construct($spec);
    }
    
    function flush() {
        unset(self::$queryCache[ $this->spec['queryCacheKey'] ]);
        $this->localMap = FALSE;
        $this->globalMap = FALSE;
    }
    
    function toLocalValue($value) {
        if (!is_array($this->localMap)) {
            $this->localMap = $this->createLocalMap();
        }
        if ($value !== FALSE && !isset($this->localMap[ $value ])) {
            throw new Exception(sprintf('Failed to map %s "%s" from global to local format (%s)',
                $this->spec['field'], $value, $this->spec['sql']
            ));
        }
        return $this->localMap[ $value ];
    }
    
    function toGlobalValue($value) {
        if (!is_array($this->globalMap)) {
            $this->globalMap = $this->createGlobalMap();
        }
        if ($value !== FALSE && !isset($this->globalMap[ $value ])) {
            throw new Exception(sprintf('Failed to map %s "%s" from local to global format (%s)',
                $this->spec['field'], $value, $this->spec['sql']
            ));
        }
        return $this->globalMap[ $value ];
    }
    
    /**
     * Build value mappings
     *
     * @return array(globaValue => localValue)
     */
    function createLocalMap() {
        if (!isset(self::$queryCache[ $this->spec['queryCacheKey'] ])) {
            $q = db_query($this->spec['sql']);
            $result = array();
            while ($row = db_fetch_object($q)) {
                $result[ $row->global ] = $row->local;
            }
            self::$queryCache[ $this->spec['queryCacheKey'] ] = $result;
        }
        return self::$queryCache[ $this->spec['queryCacheKey'] ];
    }
    
    /**
     * Build value mappings
     *
     * @return array(localValue => globalValue)
     */
    function createGlobalMap() {
        return array_flip($this->createLocalMap());
    }
}

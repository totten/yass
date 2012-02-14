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

require_once 'YASS/Filter/OptionValue.php';

/**
 * Convert prefix_id fields to global format.
 *
 * Note: Civi 2.2 defaults to formatting prefix values like "Dr" or "Mrs", but 3.1+ defaults
 * to "Dr." or "Mrs." This implementation aims to make the trailing "." irrelevant so that it
 * works with replicas on either convention.
 *
 * Note: This uses the *local* option group mappings; this may constrain which replicas can/should use it
 */
class YASS_Filter_IndividualPrefix extends YASS_Filter_OptionValue {

    /**
     *
     * @param $spec array; keys: 
     *  - entityType: string, the type of entity to which the filter applies
     *  - field: string, the incoming field name
     *  - localFormat: string, the format used on $replicaId ('value', 'name', 'label')
     *  - globalFormat: string, the format used on normalized replicas ('value', 'name', 'label')
     *  - group: string, the name of the optiongroup containing values/names/labels (alt: groupId)
     *  - groupId: int, the id of the optiongroup containing values/names/labels (alt: group) 
     */
    function __construct($spec) {
        $defaults = array(
            'group' => 'individual_prefix',
            'localFormat' => 'value',
            'globalFormat' => 'name',
        );
        $spec = array_merge($defaults, $spec);
        parent::__construct($spec);
    }
    
    /**
     * Build value mappings
     *
     * @return array(globaValue => localValue)
     */
    function createLocalMap() {
        $orig = parent::createLocalMap();
        $result = array();
        foreach ($orig as $key => $value) {
            $result[rtrim($key, '. ')] = $value;
        }
        return $result;
    }
    
    /**
     * Build value mappings
     *
     * @return array(localValue => globalValue)
     */
    function createGlobalMap() {
        // this redundant but protects against uncoordinated refactoring in the parent class
        return array_flip($this->createLocalMap());
    }        
}

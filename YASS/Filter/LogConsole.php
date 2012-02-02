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

require_once 'YASS/Context.php';
require_once 'YASS/Filter.php';

/**
 * Record a log note about every record that passes through
 */
class YASS_Filter_LogConsole extends YASS_Filter {

    /**
     *
     * @param $spec array; keys: 
     *  - file: string, file path
     */
    function __construct($spec) {
        parent::__construct($spec);
        arms_util_include_api('array');
    }
    
    function log($op, $entities, YASS_Replica $replica) {
        foreach ($entities as $entity) {
            print_r(array(
                'transferId' => YASS_Context::get('transferId'),
                'replica' => sprintf('%s@%s <#%s>', $replica->name, $host, $replica->id),
                'operation' => $op,
                'entity' => $entity,
            ));
        }
    }
    
    function toGlobal(&$entities, YASS_Replica $replica) {
        $this->log('read', $entities, $replica);
    }
    
    function toLocal(&$entities, YASS_Replica $replica) {
        $this->log('write', $entities, $replica);
    }
}

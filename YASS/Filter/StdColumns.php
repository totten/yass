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

require_once 'YASS/Filter.php';

/**
 * Filter out columns which should not be visible based on (our initial standard) security policy.
 *
 *   data['#custom']             --> keep
 *   data['#unknown']['mysite']  --> keep
 *   data['#unknown']['other']   --> remove
 *
 * This implementation will likely be replaced by something more sophisticated as our
 * requirements grow.
 */
class YASS_Filter_StdColumns extends YASS_Filter {

    /**
     *
     * @param $spec array; keys:
     */
    function __construct($spec) {
        parent::__construct($spec);
    }
    
    function toLocal(&$entities, YASS_Replica $to) {
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if (is_array($entity->data['#unknown'])) {
                foreach (array_keys($entity->data['#unknown']) as $key) {
                    if ($key == $to->name) continue;
                    unset($entity->data['#unknown'][$key]);
                }
            }
        }
    }
    
    // Master is allowed to see everything coming from replica
    // function toGlobal(&$entities, YASS_Replica $from) {
    // }
}

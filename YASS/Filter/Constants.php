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
 * A helper which copies constant values into the global representation of the entity.
 */
class YASS_Filter_Constants extends YASS_Filter {

    /**
     *
     * @param $spec array; keys: 
     *  - entityTypes: array(entityType)
     *  - delim: string, a delimiter for path expressions; defaults to '/'
     *  - constants: array(pathExpr => constantValue)
     */
    function __construct($spec) {
        if (!isset($spec['delim'])) {
            $spec['delim'] = '/';
        }
        parent::__construct($spec);
        arms_util_include_api('array');
        $this->entityTypes = drupal_map_assoc($spec['entityTypes']);
        $this->constants = array();
        foreach ($spec['constants'] as $pathExpr => $value) {
            $this->constants[] = array(
                'path' => explode($spec['delim'], $pathExpr),
                'value' => $value,
            );
        }
    }
    
    function toGlobal(&$entities, YASS_Replica $proxyReplica) {
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if (isset($this->entityTypes[$entity->entityType])) {
                foreach ($this->constants as $constant) {
                    arms_util_array_set($entity->data, $constant['path'], $constant['value']);
                }
            }
        }
    }
    
    function toLocal(&$entities, YASS_Replica $proxyReplica) {
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if (isset($this->entityTypes[$entity->entityType])) {
                foreach ($this->constants as $constant) {
                    arms_util_array_unset($entity->data, $constant['path']);
                }
            }
        }
    }
}
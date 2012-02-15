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
 * A helper which removes fields from any entity passing through.
 */
class YASS_Filter_Remove extends YASS_Filter {

    /**
     *
     * @param $spec array; keys:
     *  - entityTypes: array(entityType)
     *  - delim: string, a delimiter for path expressions; defaults to '/'
     *  - fields: array(fieldName); list of fields to remove, regardless of the direction of data flow
     *  - localFields: array(fieldName); list of field names which originate in localized entity but are removed from globalized entity
     *  - globalFields: array(fieldName); list of field names which originate in globalized entity but are removed from localized entity
     */
    function __construct($spec) {
        if (!isset($spec['delim'])) {
            $spec['delim'] = '/';
        }
        parent::__construct($spec);
        arms_util_include_api('array');
        $this->entityTypes = drupal_map_assoc($spec['entityTypes']);
        $this->fields = array(
          'fields' => array(),
          'localFields' => array(),
          'globalFields' => array(),
        );
        foreach (array('fields', 'localFields', 'globalFields') as $fieldset) {
            if (is_array($spec[$fieldset])) {
                foreach ($spec[$fieldset] as $pathExpr) {
                    $this->fields[$fieldset][] = explode($spec['delim'], $pathExpr);
                }
            }
        }
    }
    
    function toGlobal(&$entities, YASS_Replica $proxyReplica) {
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if (isset($this->entityTypes[$entity->entityType])) {
                foreach ($this->fields['fields'] as $path) {
                    arms_util_array_unset($entity->data, $path);
                }
                foreach ($this->fields['localFields'] as $path) {
                    arms_util_array_unset($entity->data, $path);
                }
            }
        }
    }
    
    function toLocal(&$entities, YASS_Replica $proxyReplica) {
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if (isset($this->entityTypes[$entity->entityType])) {
                foreach ($this->fields['fields'] as $path) {
                    arms_util_array_unset($entity->data, $path);
                }
                foreach ($this->fields['globalFields'] as $path) {
                    arms_util_array_unset($entity->data, $path);
                }
            }
        }
    }
}
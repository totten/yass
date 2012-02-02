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
 * Rename custom fields, e.g.
 *
 *   data[custom_123] <--> data[#custom][symbolic_name]
 *   data[custom_456] <--> data[#unknown][$replicaName][456]
 *
 * If the local schema includes a field ID which is not mapped to
 * a symbolic name, then it is passed through as #unknown.
 *
 * If the global schema includes a symbolc name which is not mapped
 * to a local field ID, then an exception is thrown.
 */
class YASS_Filter_CustomFieldName extends YASS_Filter {

    /**
     *
     * @param $spec array; keys:
     *  - fields: array(fieldId => symbolicName)
     */
    function __construct($spec) {
        if (!is_array($spec['fields'])) {
            $spec['fields'] = array();
        }
        parent::__construct($spec);
    }
    
    function toLocal(&$entities, YASS_Replica $to) {
        $scopeName = $to->name;
        $fieldsByName = array_flip($this->spec['fields']);
        
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if (is_array($entity->data['#custom'])) {
                foreach ($entity->data['#custom'] as $field => $value) {
                    if (isset($fieldsByName[$field])) {
                        $entity->data[ 'custom_' . $fieldsByName[$field] ] = $value;
                        unset($entity->data['#custom'][$field]);
                    } else {
                        throw new Exception(sprintf('Failed to map global=>local custom-field (replicaId=%s, entityType=%s, field=%s)',
                            $to->id, $entity->entityType, $field));
                    }
                }
            }
            if (is_array($entity->data['#unknown'][$scopeName])) {
                foreach ($entity->data['#unknown'][$scopeName] as $fid => $value) {
                    $entity->data['custom_' . $fid] = $value;
                }
            }
            if (empty($entity->data['#custom'])) {
                unset($entity->data['#custom']);
            }
            unset($entity->data['#unknown']);
        }
    }
    
    function toGlobal(&$entities, YASS_Replica $from) {
        $scopeName = $from->name;
        $fieldsById = $this->spec['fields'];
        
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if (is_array($entity->data)) {
                foreach ($entity->data as $field => $value) {
                    $matches = array();
                    if (preg_match('/^custom_(\d+)$/', $field, $matches)) {
                        $fid = $matches[1];
                        if (isset($fieldsById[$fid])) {
                            $entity->data['#custom'][$fieldsById[$fid]] = $value;
                        } else {
                            $entity->data['#unknown'][$scopeName][$fid] = $value;
                        }
                        unset($entity->data[$field]);
                    }
                }
            }
        }
    }
}

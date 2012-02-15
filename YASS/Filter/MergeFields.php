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
 * Merge an incoming entity with the pre-existing version of the entity. Notes:
 *
 * - The implementation only requires the toLocal() interface.
 * - The merge only works with entities which are encoded as array-trees
 * - The merge behavior does not need to apply to every part of the entity -- for
 *   example, one might apply merge behavior to custom fields but not to built-in
 *   fields.
 */
class YASS_Filter_MergeFields extends YASS_Filter {

    /**
     * @var array(pathArray) List of field-paths which should be merged
     */
    var $paths;

    /**
     *
     * @param $spec array; keys: 
     *  - entityTypes: array(entityType)
     *  - delim: string, a delimiter for path expressions; defaults to '/'
     *  - paths: array(pathExpr); each pathExpr is a node whose children should be merged; to merge on the root level, use an empty string
     */
    function __construct($spec) {
        if (!isset($spec['delim'])) {
            $spec['delim'] = '/';
        }
        parent::__construct($spec);
        arms_util_include_api('array');
        $this->entityTypes = drupal_map_assoc($spec['entityTypes']);
        $this->paths = array();
        foreach ($spec['paths'] as $pathExpr) {
            if ($pathExpr == '') {
                $this->paths[] = array();
            } else {
                $this->paths[] = explode($spec['delim'], $pathExpr);
            }
        }
    }
    
    function toLocal(&$entities, YASS_Replica $to) {
        // FIXME Uses unpublished interface, _getEntities, to skip filtering
        $oldEntities = $to->data->_getEntities(arms_util_array_collect($entities, 'entityGuid'));
        
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if (isset($this->entityTypes[$entity->entityType])) {
                $oldEntity = $oldEntities[$entity->entityGuid];
                if (!$oldEntity) continue;
                
                foreach ($this->paths as $path) {
                    if ($path == array()) { // root
                        $entity->data = $entity->data + $oldEntity->data;
                        continue;
                    }
                    
                    $new = arms_util_array_resolve($entity->data, $path);
                    $old = arms_util_array_resolve($oldEntity->data, $path);
                    
                    if (is_array($new) && is_array($old)) {
                        $merged = $new + $old;
                        arms_util_array_set($entity->data, $path, $merged);
                    } elseif (is_array($old)) {
                        arms_util_array_set($entity->data, $path, $old);
                    }
                    
                }
            }
        }
    }
}

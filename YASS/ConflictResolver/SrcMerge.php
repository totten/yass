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

require_once 'YASS/Engine.php';
require_once 'YASS/ConflictResolver.php';

/**
 * A conflict resolver which basically preserves the left/src entity --
 * but fills in any blank values by copying from the right/dest.
 *
 * The merge is very soft and will only create a conflict-log if
 * there was a genuine conflict in the data.
 */
class YASS_ConflictResolver_SrcMerge extends YASS_ConflictResolver {
    protected function resolve(YASS_Conflict $conflict) {
        list ($isChanged, $isConflicted) = $this->mergeFields($conflict->left->entity, $conflict->right->entity);
        if ($isChanged) {
            YASS_Context::get('addendum')->add($conflict->left->entity);
        }
        if ($isConflicted) {
            $conflict->pickLeft();
        }
    }
    
    /**
     * Fill in any blank fields of $keeperEntity with values from $destroyedEntity
     */
    function mergeFields(YASS_Entity $keeperEntity, YASS_Entity $destroyedEntity) {
        if (!$destroyedEntity->exists) return array(FALSE, FALSE);
        
        $result = array();
        $isChanged = FALSE;
        $isConflicted = FALSE;
        foreach ($destroyedEntity->data as $key => $value) {
            if ($keeperEntity->data[$key] == $value) {
                // nothing todo
            } elseif ($keeperEntity->data[$key] === NULL || $keeperEntity->data[$key] === '' || $keeperEntity->data[$key] === array()) {
                $keeperEntity->data[$key] = $value;
                $isChanged = TRUE;
            } elseif ($value) {
                $isConflicted = TRUE;
            }
        }
        return array($isChanged, $isConflicted);
    }
}

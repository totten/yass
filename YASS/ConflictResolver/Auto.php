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
require_once 'YASS/ConflictResolver/SrcMerge.php';

/**
 * A conflict resolver for use with background synchronizations in which 
 * we need to make a best-effort guess.
 *
 * Note: Src should be an ARMS site, and dest should be the master.
 *
 * In general, we use SrcWins (active-ARMS-site-wins). But in the case
 * where an item disappeared on the master, we allow the master to win.
 * This is sensible when the disappearance stems from a change in
 * access-control.
 */
class YASS_ConflictResolver_Auto extends YASS_ConflictResolver {
    function __construct() {
        $this->leftMerger = new YASS_ConflictResolver_SrcMerge();
    }

    protected function resolve(YASS_Conflict $conflict) {
        $guid = $conflict->entityGuid;
        if (!$conflict->right->entity->exists) {
            $conflict->pickRight();
        } elseif (!$conflict->left->entity->exists) {
            $conflict->pickLeft();
        } else {
            $this->leftMerger->resolveAll(array($conflict));
        }
    }
}

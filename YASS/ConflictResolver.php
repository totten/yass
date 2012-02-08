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

require_once 'YASS/IConflictResolver.php';

abstract class YASS_ConflictResolver implements YASS_IConflictResolver {

    /**
     * Resolve a batch of conflicts
     *
     * @param $conflicts array(YASS_Conflict)
     */
    function resolveAll($conflicts) {
        foreach ($conflicts as $conflict) {
            $this->resolve($conflict);
        }
    }
    
    protected abstract function resolve(YASS_Conflict $conflict);
}

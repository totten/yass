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

require_once 'YASS/ConflictResolver.php';

/**
 * Perform resolutions by delegating to a specific sequence of resolvers. The first conflict
 * is resolved by the first object; the second is resolved by the second object; etc.
 */
class YASS_ConflictResolver_Queue extends YASS_ConflictResolver {
    /**
     * @var array(YASS_IConflictResolver)
     */
    var $resolvers;
    
    function __construct($resolvers) {
        $this->resolvers = $resolvers;
    }
    
    function isEmpty() {
        return empty($this->resolvers);
    }
    
    protected function resolve(YASS_Conflict $conflict) {
        if (! $this->isEmpty()) {
            $resolver = array_shift($this->resolvers);
        } else {
            require_once 'YASS/ConflictResolver/Exception.php';
            $resolver = new YASS_ConflictResolver_Exception();
        }
        return $resolver->resolveAll(array($conflict));
    }
}

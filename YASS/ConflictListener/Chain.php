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

require_once 'YASS/IConflictListener.php';

class YASS_ConflictListener_Chain implements YASS_IConflictListener {
    /**
     * @var array(YASS_IConflictListener), sorted by weight ascending
     */
    var $listeners;

    function __construct($spec) {
        require_once 'YASS/Context.php';
        
        $this->listeners = $spec['listeners'];
        usort($this->listeners, arms_util_sort_by('weight'));
        unset($spec['listeners']);
    }
    
    function addListener(YASS_IConflictListener $listener) {
        $this->listeners[] = $listener;
        usort($this->listeners, arms_util_sort_by('weight'));
    }

    function onPickWinner(YASS_Conflict $conflict) {
        foreach ($this->listeners as $listener) {
            $listener->onPickWinner($conflict);
        }
    }
}

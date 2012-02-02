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
require_once 'YASS/Replica.php';

/**
 * Listen to the set of events in a filter chain
 *
 * @public
 */
class YASS_IFilterListener {
    function beginToGlobal(&$entities, YASS_Replica $replica) {}
    function onToGlobal(&$entities, YASS_Replica $replica, YASS_Filter $filter) {}
    function endToGlobal(&$entities, YASS_Replica $replica) {}
    function beginToLocal(&$entities, YASS_Replica $replica) {}
    function onToLocal(&$entities, YASS_Replica $replica, YASS_Filter $filter) {}
    function endToLocal(&$entities, YASS_Replica $replica) {}
}
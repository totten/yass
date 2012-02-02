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

/**
 * @public
 */
interface YASS_IFilter {

    /**
     * Modify a list of entities, converting local encodings to global encodings
     *
     * @param $entities array(YASS_Entity)
     */
    function toGlobal(&$entities, YASS_Replica $from);
    
    /**
     * Modify a list of entities, converting global encodings to local encodings
     *
     * @param $entities array(YASS_Entity)
     */
    function toLocal(&$entities, YASS_Replica $to);

}
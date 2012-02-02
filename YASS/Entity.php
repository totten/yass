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

class YASS_Entity {
    var $entityGuid;
    var $entityType;
    var $data;
    
    /**
     * @var bool; true if extant and accessible; false if non-existant or inaccessible
     */
    var $exists;
    
    function __construct($entityGuid, $entityType, $data, $exists = TRUE) {
        $this->entityGuid = $entityGuid;
        $this->entityType = $entityType;
        $this->data = $data;
        $this->exists = $exists ? TRUE : FALSE; // precaution -- enforce consistency
    }
}

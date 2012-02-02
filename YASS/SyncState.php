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

require_once 'YASS/Version.php';

class YASS_SyncState {
    
    /**
     * @var string, GUID
     */
    var $entityGuid;
    
    /**
     * @var YASS_Version
     */
    var $modified;
    
    /**
     * @var YASS_Version
     */
    var $created;
    
    function __construct($entityGuid, YASS_Version $modified, YASS_Version $created) {
        $this->entityGuid = $entityGuid;
        $this->modified = $modified;
        $this->created = $created;
    }
}
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
require_once 'YASS/Version.php';

/**
 * Record a copy of every entity that passes into the datastore (via toLocal)
 */
class YASS_Filter_Archive extends YASS_Filter {

    /**
     *
     * @param $spec array; keys: 
     */
    function __construct($spec) {
        parent::__construct($spec);
        arms_util_include_api('array');
        arms_util_include_api('query');
        require_once 'YASS/Context.php';
        require_once 'YASS/Archive.php';
    }
    
    function toGlobal(&$entities, YASS_Replica $replica) {
    }
    
    function toLocal(&$entities, YASS_Replica $replica) {
        $entityVersions = YASS_Context::get('entityVersions');
        if (!is_array($entityVersions)) {
            throw new Exception("Failed to archive entities -- entity versions are unavailable");
        }
        $archive = new YASS_Archive($replica);
        foreach ($entities as $entity) {
            $version = $entityVersions[$entity->entityGuid];
            if (! ($version instanceof YASS_Version)) {
                throw new Exception(sprintf("Failed to determine current version of entity [%s]", $entity->entityGuid));
            }
            $archive->putEntity($entity, $version);
        }
    }
}

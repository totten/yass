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

class YASS_EntityRevision extends YASS_Entity {
    /**
     * @var YASS_Version
     */
    var $version;
    
    /**
     * @var int, seconds since epoch
     */
    var $timestamp;
    
    static function createByObject(YASS_Entity $entity, YASS_Version $version) {
        return new YASS_EntityRevision(
            $entity->entityGuid,
            $entity->entityType,
            $entity->data,
            $entity->exists,
            $version,
            NULL
        );
    }
    
    static function createByArchive($row) {
        return new YASS_EntityRevision(
            $row['entity_id'],
            $row['entity_type'],
            unserialize($row['data']),
            $row['is_extant'],
            new YASS_Version($row['u_replica_id'], $row['u_tick']),
            $row['timestamp']
        );
    }

    function __construct($entityGuid, $entityType, $data, $exists, $version, $timestamp) {
        parent::__construct($entityGuid, $entityType, $data, $exists);
        $this->version = $version;
        $this->timestamp = $timestamp;
    }
}

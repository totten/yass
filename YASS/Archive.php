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

class YASS_Archive {
    function __construct(YASS_Replica $replica) {
        $this->replica = $replica;
    }

    function putEntity(YASS_Entity $entity, YASS_Version $version) {
        $archive = array(
            'replica_id' => $this->replica->id,
            'entity_type' => $entity->entityType,
            'entity_id' => $entity->entityGuid, 
            'is_extant' => $entity->exists,
            'u_replica_id' => $version->replicaId,
            'u_tick' => $version->tick,
            'data' => $entity->data,
             'timestamp' => arms_util_time(),
        );
        drupal_write_record('yass_archive', $archive);
    }
    
    /**
     * Fetch an old version of an entity
     *
     * @return YASS_Entity or FALSE
     */
    function getEntity($entityGuid, YASS_Version $version) {
        $q = db_query('
            SELECT entity_id, entity_type, data, is_extant
            FROM {yass_archive}
            WHERE replica_id = %d
            AND entity_id = "%s"
            AND u_replica_id = %d
            AND u_tick = %d
            ', $this->replica->id, $entityGuid, $version->replicaId, $version->tick);
        if ($row = db_fetch_object($q)) {
            $entities = array();
            $entities[$entityGuid] = new YASS_Entity(
                $row->entity_id,
                $row->entity_type,
                unserialize($row->data),
                $row->is_extant
            );
            $this->replica->filters->toGlobal($entities, $this->replica);
            return $entities[$entityGuid];
        } else {
            return FALSE;
        }
    }
}
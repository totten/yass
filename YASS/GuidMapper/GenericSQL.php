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

require_once 'YASS/IGuidMapper.php';
require_once 'YASS/ReplicaListener.php';

/**
 * Translate between globally-unique ID's and replica-local (type,id) pairs.
 */
class YASS_GuidMapper_GenericSQL extends YASS_ReplicaListener implements YASS_IGuidMapper {

    /**
     * @var YASS_Replica
     */
    var $replica;
    
    /**
     * List mappings, indexed by guid
     *
     * @var array(guid => stdClass{yass_guidmap})
     */
    var $byGuid;
    
    /**
     * List of mappings, indexed by type and local id
     *
     * @var array(type => array(lid => stdClass{yass_guidmap})
     */
    var $byTypeId;
    
    /**
     *
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     */
    function __construct(YASS_Replica $replica) {
        $this->replica = $replica;
        $this->byGuid = array();
        $this->byTypeId = array();
    }
    
    /**
     * Translate a local (type,id) to a GUID
     *
     * ex: $guid = $mapper->toGlobal($type,$lid);
     *
     * @param $type string
     * @param $lid int
     * @return string or FALSE
     */
    function toGlobal($type, $lid) {
        if (!isset($this->byTypeId[$type][$lid])) {
            $this->loadLocalIds(array($type => array($lid)));
        }
        if ($this->byTypeId[$type][$lid] == YASS_IGuidMapper::NOT_FOUND) {
            return FALSE;
        } else {
            return $this->byTypeId[$type][$lid]->guid;
        }
    }
    
    /**
     * Translate a GUID to a local (type,id)
     *
     * ex: list($type,$lid) = $mapper->toLocal($guid);
     *
     * @param $guid string
     * @return array(0=>type, 1=>localId) or array(FALSE,FALSE)
     */
    function toLocal($guid) {
        if (! isset($this->byGuid[$guid])) {
            $this->loadGlobalIds(array($guid));
        }
        if ($this->byGuid[$guid] == YASS_IGuidMapper::NOT_FOUND) {
            return array(FALSE,FALSE);
        } else {
            return array($this->byGuid[$guid]->entity_type, $this->byGuid[$guid]->lid);
        }
    }
    
    /**
     * Pre-fetch the mappings for a list of GUIDs
     *
     * @param $guids array(entityGuid)
     * @return array(entityGuid => stdClass('entity_type' => type, 'lid' => localId, 'guid' => entityGuid))
     */
    function loadGlobalIds($guids) {
        if (empty($guids)) {
            return array();
        }
        
        arms_util_include_api('query');
        $select = arms_util_query('{yass_guidmap}');
        $select->addSelects(array('entity_type', 'lid', 'guid'));
        $select->addWheref('replica_id = %d', $this->replica->id);
        $select->addWhere(arms_util_query_in('guid', $guids));
        $q = db_query($select->toSQL());
        $result = array();
        while ($row = db_fetch_object($q)) {
            $this->byGuid[ $row->guid ] = $row;
            $this->byTypeId[ $row->entity_type ][ $row->lid ] = $row;
            $result [] = $row;
        }
        
        // Remember unmatched GUIDs
        foreach ($guids as $guid) {
            if (!isset($this->byGuid[$guid])) {
                $this->byGuid[$guid] = YASS_IGuidMapper::NOT_FOUND;
            }
        }
        
        return $result;
    }
    
    /**
     * Convert a list of local (type,ID)s to GUIDs.
     *
     * Unmapped items do not appear in the result set
     *
     * @param $localids array(type => array(localId))
     * @return array(entityGuid => stdClass('entity_type' => type, 'lid' => localId, 'guid' => entityGuid))
     */
    function loadLocalIds($localids) {
        if (empty($localids)) {
            return array();
        }
        
        $result = array();
        foreach ($localids as $type => $ids) {
            arms_util_include_api('query');
            $select = arms_util_query('{yass_guidmap}');
            $select->addSelects(array('entity_type', 'lid', 'guid'));
            $select->addWheref('replica_id = %d', $this->replica->id);
            $select->addWhere(arms_util_query_in('lid', $ids));
            $q = db_query($select->toSQL());
            while ($row = db_fetch_object($q)) {
                $this->byGuid[ $row->guid ] = $row;
                $this->byTypeId[ $row->entity_type ][ $row->lid ] = $row;
                $result[] = $row;
            }
            
            // Remember unmatched IDs
            foreach ($ids as $id) {
                if (!isset($this->byTypeId[$type][$id])) {
                    $this->byTypeId[$type][$id] = YASS_IGuidMapper::NOT_FOUND;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Add or update mappings between GUIDs and local IDs
     *
     * @param $mappings array(type => array(localId => entityGuid))
     */
    function addMappings($mappings) {
        foreach ($mappings as $type => $idMappings) {
            foreach ($idMappings as $localId => $entityGuid) {
                $row = new stdClass();
                $row->entity_type = $type;
                $row->lid = $localId;
                $row->guid = $entityGuid;
                
                db_query('INSERT INTO {yass_guidmap} (replica_id,entity_type,lid,guid)
                    VALUES (%d,"%s",%d,"%s")
                    ON DUPLICATE KEY UPDATE guid = "%s"
                ', $this->replica->id, $row->entity_type, $row->lid, $row->guid, $row->guid);

                $this->byGuid[ $row->guid ] = $row;
                $this->byTypeId[ $row->entity_type ][ $row->lid ] = $row;
            }
        }
    }
    
    /**
     * Permanently erase mappings
     */
    function destroy() {
        db_query('DELETE FROM {yass_guidmap} WHERE replica_id=%d', $this->replica->id);
        $this->byGuid = array();
        $this->byTypeId = array();
    }
    
    /**
     * Flush any mappings that are cached in memory
     */
    function flush() {
        $this->byGuid = array();
        $this->byTypeId = array();
    }

    function onChangeId(YASS_Replica $replica, $oldId, $newId) {
        db_query('UPDATE {yass_guidmap} SET replica_id=%d WHERE replica_id=%d', $newId, $oldId);
    }
}

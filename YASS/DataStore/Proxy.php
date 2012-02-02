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

require_once 'YASS/Proxy.php';
require_once 'YASS/Entity.php';
require_once 'YASS/IDataStore.php';

/**
 * A datastore which uses arms_interlink RPC calls to synchronize against a remote datastore
 */
class YASS_DataStore_Proxy extends YASS_Proxy implements YASS_IDataStore {

    /**
     * @var YASS_Replica
     */
    var $replica;
    
    /**
     * 
     */
    public function __construct(YASS_Replica $replica) {
        module_load_include('service.inc', 'yass');
        $this->replica = $replica;
        parent::__construct($replica->spec['remoteSite'], $replica->spec['remoteReplica'], $replica);
    }

    /**
     * Get the content of several entities
     *
     * @param $entityGuids array(entityGuid)
     * @return array(entityGuid => YASS_Entity)
     */
    function getEntities($entityGuids) {
        $entities = $this->_getEntities($entityGuids);
        $this->replica->filters->toGlobal($entities, $this->replica);
        return $entities;
    }
     
    /**
     * Get the content of several entities
     *
     * @param $entityGuids array(entityGuid)
     * @return array(entityGuid => YASS_Entity)
     */
    function _getEntities($entityGuids) {
        $result = $this->_proxy('yass.getEntities', $entityGuids);
        YASS_Proxy::decodeAllInplace('YASS_Entity', $result);
        return $result;
    }

    /**
     * Save an entity
     *
     * @param $entities array(YASS_Entity)
     */
    function putEntities($entities) {
        $this->replica->filters->toLocal($entities, $this->replica);
        return $this->_putEntities($entities);
    }
    
    /**
     * Save an entity
     *
     * @param $entities array(YASS_Entity)
     */
    function _putEntities($entities) {
        YASS_Proxy::encodeAllInplace('YASS_Entity', $entities);
        $this->_proxy('yass.putEntities', $entities);
    }
    
}

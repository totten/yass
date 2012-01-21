<?php

require_once 'YASS/Entity.php';

interface YASS_IDataStore {
     
    /**
     * Get the content of several entities
     *
     * @param $entityGuids array(entityGuid)
     * @return array(entityGuid => YASS_Entity)
     */
    function getEntities($entityGuids);

    /**
     * Save an entity
     *
     * @param $entities array(YASS_Entity)
     */
    function putEntities($entities);
    
}

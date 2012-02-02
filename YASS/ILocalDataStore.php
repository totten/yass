<?php

/**
 * A local data store is like a regular data-store, except that all entities have locally-assigned IDs
 * instead of GUIDs, and the operations for manipulating entities are generally broken up by type.
 */
interface YASS_ILocalDataStore {

    /**
     * Detremine the order in which entities should be written to DB.
     *
     * Low-weight items are inserted before high-weight items.
     * High-weight items are deleted before low-weight items.
     *
     * @return array(entityType => weight)
     */
    function getEntityWeights();

    /**
     * Read a batch of entities
     *
     * @var $lids array(entityGuid => lid)
     * @return array(entityGuid => YASS_Entity)
     */
    function getEntities($entityType, $lids);
    
    /**
     * Get a list of all entities
     *
     * This is an optional interface to facilitate testing/debugging
     *
     * @return array(entityGuid => YASS_Entity)
     */
    function getAllEntitiesDebug($entityType, YASS_GuidMapper $mapper);
    
    /**
     * Add a new entity and generate a new local-id
     *
     * @return local id
     * @throws Exception
     */
    function insert($entityType, YASS_Entity $entity);
    
    /**
     * Insert or update an entity using a specific local-id
     *
     * @return void
     * @throws Exception
     */
    function insertUpdate($entityType, $lid, YASS_Entity $entity);
    
    /**
     * Delete an entity
     */
    function delete($entityType, $lid);
}

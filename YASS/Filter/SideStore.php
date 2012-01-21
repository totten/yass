<?php

require_once 'YASS/Filter.php';
require_once 'YASS/Version.php';

/**
 * Extract a handful of incoming fields and store in a secondary datastore.
 *
 * Note: Only works with array-based entities
 */
class YASS_Filter_SideStore extends YASS_Filter {

    /**
     * @var array( exploded-paths )
     */
    var $paths;

    /**
     *
     * @param $spec array; keys: 
     *  - sideStore: string, name of another datastore
     *  - paths: array(string), list of paths to move to sideStore
     *  - delim: string, a delimiter for path expressions; defaults to '/'
     *  - extract: callback, a function which moves data from $entity to $sideEntity
     *  - merge: callback, a function which moves data from $sideEntity to $entity
     *
     * The simplest usage is to supply a list of 'paths'. You may omit and instead
     * provide extract/merge callbacks or override the extract()/merge() functions
     */
    function __construct($spec) {
        if (!isset($spec['extract'])) {
            $spec['extract'] = array($this, 'movePaths');
        }
        if (!isset($spec['merge'])) {
            $spec['merge'] = array($this, 'movePaths');
        }
        if (!isset($spec['delim'])) {
            $spec['delim'] = '/';
        }
        
        parent::__construct($spec);
        arms_util_include_api('array');
        require_once 'YASS/Engine.php';
        
        if (is_array($spec['paths'])) {
            $this->paths = array();
            foreach ($spec['paths'] as $path) {
                $this->paths[] = explode($spec['delim'], $path);
            }
        }
    }
    
    /**
     * Combine the data from the pipeline with data from the sideStore to produce the global variants.
     */
    function toGlobal(&$entities, YASS_Replica $replica) {
        $sideStore = YASS_Engine::singleton()->getReplicaByName($this->spec['sideStore']);
        if (!is_object($sideStore)) {
            throw new Exception(sprintf('Failed to locate sideStore [%s]', $this->spec['sideStore']));
        }
        
        $sideEntities = $sideStore->data->getEntities(arms_util_array_collect($entities, 'entityGuid'));
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if (!$sideEntities[$entity->entityGuid]->exists) continue;
            $this->merge($sideEntities[$entity->entityGuid], $entity);
        }
    }
    
    /**
     * Split data from the pipeline, with some fields going to the sideStore -- and the others remaining in the pipeline
     */
    function toLocal(&$entities, YASS_Replica $replica) {
        $sideStore = YASS_Engine::singleton()->getReplicaByName($this->spec['sideStore']);
        if (!is_object($sideStore)) {
            throw new Exception(sprintf('Failed to locate sideStore [%s]', $this->spec['sideStore']));
        }
        
        $sideEntities = array();
        foreach ($entities as $entity) {
            if ($entity->exists) {
                $sideEntity = new YASS_Entity($entity->entityGuid, $entity->entityType, array(), $entity->exists);
                $this->extract($entity, $sideEntity);
                $sideEntities[ $sideEntity->entityGuid ] = $sideEntity;
            } else {
                // tombstone; no data to extract
                $sideEntity = new YASS_Entity($entity->entityGuid, $entity->entityType, $entity->data, $entity->exists);
                $sideEntities[ $sideEntity->entityGuid ] = $sideEntity;
            }
        }
        $sideStore->data->putEntities($sideEntities);
    }
    
    /**
     * Pull out fields from $from and move them to sideStore's $to (toLocal)
     */
    function extract(YASS_Entity $from, YASS_Entity $to) {
        call_user_func($this->spec['extract'], $from, $to);
    }
    
    /**
     * Merge fields from sideStore's $from into $to (toGlobal)
     */
    function merge(YASS_Entity $from, YASS_Entity $to) {
        call_user_func($this->spec['merge'], $from, $to);
    }
    
    function movePaths(YASS_Entity $from, YASS_Entity $to) {
        foreach ($this->paths as $path) {
            arms_util_array_set($to->data, $path,
                arms_util_array_resolve($from->data, $path)
            );
            arms_util_array_unset($from->data, $path);
        }
    }
}

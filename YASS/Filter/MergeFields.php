<?php

require_once 'YASS/Filter.php';

/**
 * Merge an incoming entity with the pre-existing version of the entity. Notes:
 *
 * - The implementation only requires the toLocal() interface.
 * - The merge only works with entities which are encoded as array-trees
 * - The merge behavior does not need to apply to every part of the entity -- for
 *   example, one might apply merge behavior to custom fields but not to built-in
 *   fields.
 */
class YASS_Filter_MergeFields extends YASS_Filter {

	/**
	 * @var array(pathArray) List of field-paths which should be merged
	 */
	var $paths;

	/**
	 *
	 * @param $spec array; keys: 
	 *  - entityTypes: array(entityType)
	 *  - delim: string, a delimiter for path expressions; defaults to '/'
	 *  - paths: array(pathExpr); each pathExpr is a node whose children should be merged
	 */
	function __construct($spec) {
		if (!isset($spec['delim'])) {
			$spec['delim'] = '/';
		}
		parent::__construct($spec);
		arms_util_include_api('array');
		$this->entityTypes = drupal_map_assoc($spec['entityTypes']);
		$this->paths = array();
		foreach ($spec['paths'] as $pathExpr) {
			$this->paths[] = explode($spec['delim'], $pathExpr);
		}
	}
	
	function toLocal(&$entities, YASS_Replica $to) {
		// FIXME Uses unpublished interface, _getEntities, to skip filtering
		$oldEntities = $to->data->_getEntities(arms_util_array_collect($entities, 'entityGuid'));
		
		foreach ($entities as $entity) {
			if (!$entity->exists) continue;
			if (isset($this->entityTypes[$entity->entityType])) {
				$oldEntity = $oldEntities[$entity->entityGuid];
				if (!$oldEntity) continue;
				
				foreach ($this->paths as $path) {
					$new = arms_util_array_resolve($entity->data, $path);
					$old = arms_util_array_resolve($oldEntity->data, $path);
					
					if (is_array($new) && is_array($old)) {
					  $merged = $new + $old;
					  arms_util_array_set($entity->data, $path, $merged);
					} elseif (is_array($old)) {
					  arms_util_array_set($entity->data, $path, $old);
					}
					
				}
			}
		}
	}
}

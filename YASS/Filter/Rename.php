<?php

require_once 'YASS/Filter.php';

/**
 * A filter which renames a field
 */
class YASS_Filter_Rename extends YASS_Filter {
	
	/**
	 *
	 * Note: arms_util_array_combine_properties(YASS_Engine::singleton()->getReplicas(), 'name', 'id')
	 *
	 * @param $spec array; keys:
	 *  - entityTypes: array(entityType)
	 *  - global: string, name of the global field which stores the list of authorized replica names
	 *  - local: string, name of the local field which stores the list of authorized replica names
	 */
	function __construct($spec) {
		if (!isset($spec['delim'])) {
			$spec['delim'] = '/';
		}
		parent::__construct($spec);
		arms_util_include_api('array');
		$this->entityTypes = drupal_map_assoc($spec['entityTypes']);
		$this->globalPath = explode($spec['delim'], $spec['global']);
		$this->localPath =  explode($spec['delim'], $spec['local']);
	}
	
	function toLocal(&$entities, YASS_Replica $replica) {
		foreach ($entities as $entity) {
			if (!$entity->exists) continue;
			if (isset($this->entityTypes[$entity->entityType])) {
				arms_util_array_set($entity->data, $this->localPath, 
					arms_util_array_resolve($entity->data, $this->globalPath)
				);
				arms_util_array_unset($entity->data, $this->globalPath);
			}
		}
	}
	
	function toGlobal(&$entities, YASS_Replica $replica) {
		foreach ($entities as $entity) {
			if (!$entity->exists) continue;
			if (isset($this->entityTypes[$entity->entityType])) {
				arms_util_array_set($entity->data, $this->globalPath, 
					arms_util_array_resolve($entity->data, $this->localPath)
				);
				arms_util_array_unset($entity->data, $this->localPath);
			}
		}
	}
}

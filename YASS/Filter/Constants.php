<?php

require_once 'YASS/Filter.php';

/**
 * A helper which copies constant values into the global representation of the entity.
 */
class YASS_Filter_Constants extends YASS_Filter {

	/**
	 *
	 * @param $spec array; keys: 
	 *  - entityTypes: array(entityType)
	 *  - delim: string, a delimiter for path expressions; defaults to '/'
	 *  - constants: array(pathExpr => constantValue)
	 */
	function __construct($spec) {
		if (!isset($spec['delim'])) {
			$spec['delim'] = '/';
		}
		parent::__construct($spec);
		arms_util_include_api('array');
		$this->entityTypes = drupal_map_assoc($spec['entityTypes']);
		$this->constants = array();
		foreach ($spec['constants'] as $pathExpr => $value) {
			$this->constants[] = array(
				'path' => explode($spec['delim'], $pathExpr),
				'value' => $value,
			);
		}
	}
	
	function toGlobal(&$entities, YASS_Replica $proxyReplica) {
		foreach ($entities as $entity) {
			if (isset($this->entityTypes[$entity->entityType])) {
				foreach ($this->constants as $constant) {
					arms_util_array_set($entity->data, $constant['path'], $constant['value']);
				}
			}
		}
	}
	
	function toLocal(&$entities, YASS_Replica $proxyReplica) {
		foreach ($entities as $entity) {
			if (isset($this->entityTypes[$entity->entityType])) {
				foreach ($this->constants as $constant) {
					arms_util_array_unset($entity->data, $constant['path']);
				}
			}
		}
	}
}
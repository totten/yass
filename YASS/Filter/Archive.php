<?php

require_once 'YASS/Filter.php';
require_once 'YASS/Version.php';

/**
 * Record a copy of every entity that passes into the datastore (via toLocal)
 */
class YASS_Filter_Archive extends YASS_Filter {

	/**
	 *
	 * @param $spec array; keys: 
	 */
	function __construct($spec) {
		parent::__construct($spec);
		arms_util_include_api('array');
		arms_util_include_api('query');
		require_once 'YASS/Context.php';
	}
	
	function toGlobal(&$entities, YASS_Replica $replica) {
	}
	
	function toLocal(&$entities, YASS_Replica $replica) {
		$entityVersions = YASS_Context::get('entityVersions');
		if (!is_array($entityVersions)) {
			throw new Exception("Failed to archive entities -- entity versions are unavailable");
		}
		foreach ($entities as $entity) {
			$version = $entityVersions[$entity->entityGuid];
			if (! ($version instanceof YASS_Version)) {
				throw new Exception(sprintf("Failed to determine current version of entity [%s]", $entity->entityGuid));
			}
			$archive = array(
				'replica_id' => $replica->id,
				'entity_type' => $entity->entityType,
				'entity_id' => $entity->entityGuid, 
				'is_extant' => $entity->exists,
				'u_replica_id' => $version->replicaId,
				'u_tick' => $version->tick,
				'data' => serialize($entity->data),
			);
			drupal_write_record('yass_archive', $archive);
		}
	}
}

<?php

require_once 'YASS/Replica.php';

class YASS_Replica_Persistent extends YASS_Replica {
	
	/**
	 * Construct a replica based on saved configuration metadata
	 *
	 * @param $metadata array{yass_replicas} Specification for the replica
	 */
	function __construct($metadata) {
		$this->name = $metadata['name'];
		$this->id = $metadata['id'];
		$this->isActive = $metadata['is_active'];
		$this->data = $this->_createDatastore($metadata);
		$this->sync = $this->_createSyncstore($metadata);
	}
	
	/**
	 * Instantiate a sync store
	 *
	 * @param $metadata array{yass_replicas} Specification for the replica
	 * @return YASS_SyncStore
	 */
	function _createSyncstore($metadata) {
		switch ($metadata['syncstore']) {
			// whitelist
			case 'Memory':
			case 'SQL':
				require_once sprintf('YASS/SyncStore/%s.php', $metadata['syncstore']);
				$class = new ReflectionClass('YASS_SyncStore_' . $metadata['syncstore']);
				return $class->newInstance($metadata);
			default:
				return FALSE;
		}
	}
	
	/**
	 * Instantiate a data store
	 *
	 * @param $metadata array{yass_replicas} Specification for the replica
	 * @return YASS_DataStore
	 */
	function _createDatastore($metadata) {
		switch ($metadata['datastore']) {
			// whitelist
			case 'Memory':
			case 'SQL':
				require_once sprintf('YASS/DataStore/%s.php', $metadata['datastore']);
				$class = new ReflectionClass('YASS_DataStore_' . $metadata['datastore']);
				return $class->newInstance($metadata);
			default:
				return FALSE;
		}
	}
}

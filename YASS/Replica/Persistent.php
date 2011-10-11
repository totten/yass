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
	
}

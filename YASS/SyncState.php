<?php

require_once 'YASS/Version.php';

class YASS_SyncState {
	/**
	 * @var string
	 */
	var $entityType;
	
	/**
	 * @var string, GUID
	 */
	var $entityGuid;
	
	/**
	 * @var YASS_Version
	 */
	var $modified;
	
	/**
	 * @var YASS_Version
	 */
	var $created;
	
	function __construct($entityType, $entityGuid, YASS_Version $modified, YASS_Version $created) {
		$this->entityType = $entityType;
		$this->entityGuid = $entityGuid;
		$this->modified = $modified;
		$this->created = $created;
	}
}
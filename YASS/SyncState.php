<?php

require_once 'YASS/Version.php';

class YASS_SyncState {
	
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
	
	function __construct($entityGuid, YASS_Version $modified, YASS_Version $created) {
		$this->entityGuid = $entityGuid;
		$this->modified = $modified;
		$this->created = $created;
	}
}
<?php

class YASS_Entity {
	var $entityGuid;
	var $entityType;
	var $data;
	
	function __construct($entityGuid, $entityType, $data) {
		$this->entityGuid = $entityGuid;
		$this->entityType = $entityType;
		$this->data = $data;
	}
}

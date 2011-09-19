<?php

class YASS_Entity {
	var $entityType;
	var $entityGuid;
	var $data;
	
	function __construct($entityType, $entityGuid, $data) {
		$this->entityType = $entityType;
		$this->entityGuid = $entityGuid;
		$this->data = $data;
	}
}

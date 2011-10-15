<?php

class YASS_Entity {
	var $entityGuid;
	var $data;
	
	function __construct($entityGuid, $data) {
		$this->entityGuid = $entityGuid;
		$this->data = $data;
	}
}

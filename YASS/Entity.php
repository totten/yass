<?php

class YASS_Entity {
    var $entityGuid;
    var $entityType;
    var $data;
    
    /**
     * @var bool; true if extant and accessible; false if non-existant or inaccessible
     */
    var $exists;
    
    function __construct($entityGuid, $entityType, $data, $exists = TRUE) {
        $this->entityGuid = $entityGuid;
        $this->entityType = $entityType;
        $this->data = $data;
        $this->exists = $exists ? TRUE : FALSE; // precaution -- enforce consistency
    }
}

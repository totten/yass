<?php

class YASS_Version {

    /**
     * @var string
     */
    var $replicaId;
    
    /**
     * @var int
     */
    var $tick;
    
    function __construct($replicaId, $tick) {
        $this->replicaId = $replicaId;
        $this->tick = $tick;
    }
    
    function next() {
        return new YASS_Version($this->replicaId, 1+$this->tick);
    }
}

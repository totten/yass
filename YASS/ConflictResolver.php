<?php

require_once 'YASS/IConflictResolver.php';

abstract class YASS_ConflictResolver implements YASS_IConflictResolver {

    /**
     * Resolve a batch of conflicts
     *
     * @param $conflicts array(YASS_Conflict)
     */
    function resolveAll($conflicts) {
        foreach ($conflicts as $conflict) {
            $this->resolve($conflict);
        }
    }
    
    abstract function resolve(YASS_Conflict $conflict);
}

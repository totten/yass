<?php

require_once 'YASS/Conflict.php';

interface YASS_IConflictResolver {

    /**
     * Resolve a batch of conflicts
     *
     * @param $conflicts array(YASS_Conflict)
     */
    function resolveAll($conflicts);
}

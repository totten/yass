<?php

require_once 'YASS/Engine.php';
require_once 'YASS/ConflictResolver.php';

class YASS_ConflictResolver_DstWins extends YASS_ConflictResolver {
    function resolve(YASS_Conflict $conflict) {
        $conflict->pickRight();
    }
}

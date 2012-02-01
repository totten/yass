<?php

require_once 'YASS/Conflict.php';

interface YASS_IConflictResolver {
    function resolve(YASS_Conflict $conflict);
}


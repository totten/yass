<?php

require_once 'YASS/Conflict.php';

interface YASS_IConflictListener {
    function onPickWinner(YASS_Conflict $conflict);
}

<?php

require_once 'YASS/IConflictListener.php';

class YASS_ConflictListener_Chain implements YASS_IConflictListener {
    /**
     * @var array(YASS_IConflictListener), sorted by weight ascending
     */
    var $listeners;

    function __construct($spec) {
        require_once 'YASS/Context.php';
        
        $this->listeners = $spec['listeners'];
        usort($this->listeners, arms_util_sort_by('weight'));
        unset($spec['listeners']);
    }
    
    function addListener(YASS_IConflictListener $listener) {
        $this->listeners[] = $listener;
        usort($this->listeners, arms_util_sort_by('weight'));
    }

    function onPickWinner(YASS_Conflict $conflict) {
        foreach ($this->listeners as $listener) {
            $listener->onPickWinner($conflict);
        }
    }
}

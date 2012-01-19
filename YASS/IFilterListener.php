<?php

require_once 'YASS/Filter.php';
require_once 'YASS/Replica.php';

/**
 * Listen to the set of events in a filter chain
 */
class YASS_IFilterListener {
	function beginToGlobal(&$entities, YASS_Replica $replica) {}
	function onToGlobal(&$entities, YASS_Replica $replica, YASS_Filter $filter) {}
	function endToGlobal(&$entities, YASS_Replica $replica) {}
	function beginToLocal(&$entities, YASS_Replica $replica) {}
	function onToLocal(&$entities, YASS_Replica $replica, YASS_Filter $filter) {}
	function endToLocal(&$entities, YASS_Replica $replica) {}
}
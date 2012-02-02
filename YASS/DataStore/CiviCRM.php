<?php

require_once 'YASS/DataStore/Local.php';
require_once 'YASS/Replica.php';

/**
 * Provide backwards compatibility with replicas declared as 'CiviCRM'
 */
class YASS_DataStore_CiviCRM extends YASS_DataStore_Local {

    /**
     * 
     */
    public function __construct(YASS_Replica $replica) {
        arms_util_include_api('array');
        arms_util_include_api('query');
        require_once 'YASS/LocalDataStore/CiviCRM.php';
        parent::__construct($replica, new YASS_LocalDataStore_CiviCRM($replica));
    }
}

<?php

/**
 * Data-access functions for reading merge logs.
 *
 * Note: Current implementation is only suitable for use with variants of YASS_DataStore_Local
 */
class YASS_MergeLogs {
    var $maxSize = 1000;
    var $cache = array(); // array(entityType => array(destroyed_id => valid_id))
    // FIXME limit
    
    function toValidId($type, $lid) {
        if (!isset($this->cache[$type][$lid])) {
            $this->trim();
            $q = db_query('SELECT kept_id FROM {yass_mergelog} WHERE entity_type = "%s" AND destroyed_id = %d', $type, $lid);
            $newLid = db_result($q);
            if ($newLid) {
                $this->cache[$type][$lid] = $this->toValidId($type, $newLid);
            } else {
                $this->cache[$type][$lid] = $lid;
            }
        }
        return $this->cache[$type][$lid];
    }
    
    function flush() {
        $this->cache = array();
    }
    
    function trim() {
        while (count($this->cache) > $this->maxSize) {
            array_shift($this->cache);
        }
    }
}

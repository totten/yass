<?php

require_once 'YASS/Filter.php';

/**
 * Convert field values by using a local SQL lookup table 
 *
 * Note: This uses the *local* SQL-backed mappings; this may constrain which replicas can/should use it
 */
class YASS_Filter_SQLMap extends YASS_Filter {

  /**
   * Cache SQL-map queries (e.g. "select id, iso_code from civicrm_country") because they may be redundant
   *
   * @var array(queryCacheKey => array(globalValue => localValue))
   */
  static $queryCache = array();

  /**
   *
   * @param $spec array; keys: 
   *  - entityType: string, the type of entity to which the filter applies
   *  - field: string, the incoming field name
   *  - sql: string, SELECT query which returns tuples with "local" and "global" columns
   */
  function __construct($spec) {
    parent::__construct($spec);
    $spec['queryCacheKey'] = md5($spec['sql']);
    $this->spec = $spec;
  }
  
  function flush() {
    unset(self::$queryCache[ $this->spec['queryCacheKey'] ]);
  }
  
  function toLocal(&$entities, YASS_Replica $from, YASS_Replica $to) {
    $localMap = $this->getToLocalMap();
    $field = $this->spec['field'];
    $entityType = $this->spec['entityType'];
    
    foreach ($entities as $entity) {
      if ($entity->entityType == $entityType && isset($entity->data[$field])) {
        if ($entity->data[$field] !== FALSE && !isset($localMap[ $entity->data[$field] ])) {
          throw new Exception(sprintf('Failed to map %s "%s" from global to local format (%s)',
            $field, $entity->data[$field], $this->spec['sql']
          ));
        }
        $entity->data[$field] = $localMap[ $entity->data[$field] ];
      }
    }
  }
  
  function toGlobal(&$entities, YASS_Replica $from, YASS_Replica $to) {
    $globalMap = $this->getToGlobalMap();
    $field = $this->spec['field'];
    $entityType = $this->spec['entityType'];
    
    foreach ($entities as $entity) {
      if ($entity->entityType == $entityType && isset($entity->data[$field])) {
        if ($entity->data[$field] !== FALSE && !isset($globalMap[ $entity->data[$field] ])) {
          throw new Exception(sprintf('Failed to map %s "%s" from local to global format (%s)',
            $field, $entity->data[$field], $this->spec['sql']
          ));
        }
        $entity->data[$field] = $globalMap[ $entity->data[$field] ];
      }
    }
  }
  
  /**
   * Build value mappings
   *
   * @return array(globaValue => localValue)
   */
  function getToLocalMap() {
    if (!isset(self::$queryCache[ $this->spec['queryCacheKey'] ])) {
      printf("query: %s\n", $this->spec['sql']);
      $q = db_query($this->spec['sql']);
      $result = array();
      while ($row = db_fetch_object($q)) {
        $result[ $row->global ] = $row->local;
      }
      self::$queryCache[ $this->spec['queryCacheKey'] ] = $result;
    }
    return self::$queryCache[ $this->spec['queryCacheKey'] ];
  }
  
  /**
   * Build value mappings
   *
   * @return array(localValue => globalValue)
   */
  function getToGlobalMap() {
    return array_flip($this->getToLocalMap());
  }
}

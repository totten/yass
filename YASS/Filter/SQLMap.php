<?php

require_once 'YASS/Filter.php';

/**
 * Convert field values by using a local SQL lookup table 
 *
 * Note: This uses the *local* SQL-backed mappings; this may constrain which replicas can/should use it
 */
class YASS_Filter_SQLMap extends YASS_Filter {

  /**
   *
   * @param $spec array; keys: 
   *  - entityType: string, the type of entity to which the filter applies
   *  - field: string, the incoming field name
   *  - sql: string, SELECT query which returns tuples with "local" and "global" columns
   */
  function __construct($spec) {
    parent::__construct($spec);
    $this->spec = $spec;
    $this->flush();
  }
  
  function flush() {
    $this->localMap = FALSE;
    $this->globalMap = FALSE;
  }
  
  function toLocal(&$entities, YASS_Replica $from, YASS_Replica $to) {
    if (!is_array($this->localMap)) {
      $this->localMap = $this->getToLocalMap();
    }
    $field = $this->spec['field'];
    $entityType = $this->spec['entityType'];
    
    foreach ($entities as $entity) {
      if ($entity->entityType == $entityType && isset($entity->data[$field])) {
        if ($entity->data[$field] !== FALSE && !isset($this->localMap[ $entity->data[$field] ])) {
          throw new Exception(sprintf('Failed to map %s "%s" from global to local format (%s)',
            $field, $entity->data[$field], $this->spec['sql']
          ));
        }
        $entity->data[$field] = $this->localMap[ $entity->data[$field] ];
      }
    }
  }
  
  function toGlobal(&$entities, YASS_Replica $from, YASS_Replica $to) {
    if (!is_array($this->globalMap)) {
      $this->globalMap = $this->getToGlobalMap();
    }
    $field = $this->spec['field'];
    $entityType = $this->spec['entityType'];
    
    foreach ($entities as $entity) {
      if ($entity->entityType == $entityType && isset($entity->data[$field])) {
        if ($entity->data[$field] !== FALSE && !isset($this->globalMap[ $entity->data[$field] ])) {
          throw new Exception(sprintf('Failed to map %s "%s" from local to global format (%s)',
            $field, $entity->data[$field], $this->spec['sql']
          ));
        }
        $entity->data[$field] = $this->globalMap[ $entity->data[$field] ];
      }
    }
  }
  
  /**
   * Build value mappings
   *
   * @return array(globaValue => localValue)
   */
  function getToLocalMap() {
    $q = db_query($this->spec['sql']);
    $result = array();
    while ($row = db_fetch_object($q)) {
      $result[ $row->global ] = $row->local;
    }
    return $result;
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

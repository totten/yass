<?php

require_once 'YASS/Filter.php';

/**
 * Implement a helper which filters/maps values for one field in one entity. It
 * can be instantiated directly (in which case one should provide callbacks named
 * 'toLocalValue' and  'toGlobalValue') or it can be subclassed (in which case
 * one should override functions named 'toLocalValue' and 'toGlobalValue').
 */
class YASS_Filter_FieldValue extends YASS_Filter {

  /**
   *
   * @param $spec array; keys: 
   *  - entityType: string, the type of entity to which the filter applies
   *  - field: string, the incoming field name
   *  - isMultiple: whether the field contains a single value or multiple values (array)
   *  - toLocalValue: callback
   *  - toGlobalValue: callback
   */
  function __construct($spec) {
    parent::__construct($spec);
  }
  
  function toLocal(&$entities, YASS_Replica $from, YASS_Replica $to) {
    $field = $this->spec['field'];
    $entityType = $this->spec['entityType'];
    
    if ($this->spec['isMultiple']) {
      foreach ($entities as $entity) {
        if ($entity->entityType == $entityType && isset($entity->data[$field])) {
          foreach ($entity->data[$field] as $k => $v) {
            $entity->data[$field][$k] = $this->toLocalValue($v);
          }
        }
      }
    } else {
      foreach ($entities as $entity) {
        if ($entity->entityType == $entityType && isset($entity->data[$field])) {
          $entity->data[$field] = $this->toLocalValue($entity->data[$field]);
        }
      }
    }
  }
  
  function toLocalValue($value) {
    return call_user_func($this->spec['toLocalValue'], $value);
  }
  
  function toGlobal(&$entities, YASS_Replica $from, YASS_Replica $to) {
    $field = $this->spec['field'];
    $entityType = $this->spec['entityType'];
    
    if ($this->spec['isMultiple']) {
      foreach ($entities as $entity) {
        if ($entity->entityType == $entityType && isset($entity->data[$field])) {
          foreach ($entity->data[$field] as $k => $v) {
            $entity->data[$field][$k] = $this->toGlobalValue($v);
          }
        }
      }
    } else {
      foreach ($entities as $entity) {
        if ($entity->entityType == $entityType && isset($entity->data[$field])) {
          $entity->data[$field] = $this->toGlobalValue($entity->data[$field]);
        }
      }
    }
  }
  
  function toGlobalValue($value) {
    return call_user_func($this->spec['toGlobalValue'], $value);
  }
}

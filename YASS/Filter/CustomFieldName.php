<?php

require_once 'YASS/Filter.php';

/**
 * Rename custom fields, e.g.
 *
 *   data[custom_123] <--> data[#unknown][$replicaName][123]
 *
 * This 
 */
class YASS_Filter_CustomFieldName extends YASS_Filter {

  /**
   *
   * @param $spec array; keys: 
   */
  function __construct($spec) {
    parent::__construct($spec);
  }
  
  function toLocal(&$entities, YASS_Replica $to) {
    $scopeName = $to->name;
    
    foreach ($entities as $entity) {
      if (is_array($entity->data['#unknown'][$scopeName])) {
        foreach ($entity->data['#unknown'][$scopeName] as $fid => $value) {
          $entity->data['custom_' . $fid] = $value;
        }
      }
      unset($entity->data['#unknown']);
    }
  }
  
  function toGlobal(&$entities, YASS_Replica $from) {
    $scopeName = $from->name;
    
    foreach ($entities as $entity) {
      if (is_array($entity->data)) {
        foreach ($entity->data as $field => $value) {
          $matches = array();
          if (preg_match('/^custom_(\d+)$/', $field, $matches)) {
            $entity->data['#unknown'][$scopeName][ $matches[1] ] = $value;
            unset($entity->data[$field]);
          }
        }
      }
    }
  }
}

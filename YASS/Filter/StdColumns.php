<?php

require_once 'YASS/Filter.php';

/**
 * Filter out columns which should not be visible based on (our initial standard) security policy.
 *
 *   data['#custom']             --> keep
 *   data['#unknown']['mysite']  --> keep
 *   data['#unknown']['other']   --> remove
 *
 * This implementation will likely be replaced by something more sophisticated as our
 * requirements grow.
 */
class YASS_Filter_StdColumns extends YASS_Filter {

  /**
   *
   * @param $spec array; keys:
   */
  function __construct($spec) {
    parent::__construct($spec);
  }
  
  function toLocal(&$entities, YASS_Replica $to) {
    foreach ($entities as $entity) {
      if (!$entity->exists) continue;
      if (is_array($entity->data['#unknown'])) {
        foreach (array_keys($entity->data['#unknown']) as $key) {
          if ($key == $to->name) continue;
          unset($entity->data['#unknown'][$key]);
        }
      }
    }
  }
  
  // Master is allowed to see everything coming from replica
  // function toGlobal(&$entities, YASS_Replica $from) {
  // }
}

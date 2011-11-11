<?php

class YASS_Filter {

  /**
   * @var int; indicates order of execution. For toGlobal(), filters are called in order of ascending weight; for toLocal(), descending.
   */
  var $weight;

  /**
   * @var array, the original specification which produced this filter
   */
  var $spec;

  function __construct($spec) {
    $this->weight = empty($spec['weight']) ? 0 : $spec['weight'];
    $this->spec = $spec;
  }
  
  /**
   * Modify a list of entities, converting local encodings to global encodings
   *
   * @param $entities array(YASS_Entity)
   */
  function toGlobal(&$entities, YASS_Replica $src, YASS_Replica $dest) {
  }
  
  /**
   * Modify a list of entities, converting global encodings to local encodings
   *
   * @param $entities array(YASS_Entity)
   */
  function toLocal(&$entities, YASS_Replica $src, YASS_Replica $dest) {
  }

}
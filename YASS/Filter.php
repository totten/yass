<?php

class YASS_Filter {

  /**
   * @var int; indicates order of execution. For toGlobal(), filters are called in order of ascending weight; for toLocal(), descending.
   *
   * Ex: $r1->data->getEntities()
   *       => $r1->filters[wgt=1]->toGlobal() 
   *       => $r1->filters[wgt=9]->toGlobal() 
   *       => $r2->filters[wgt=9]->toLocal()
   *       => $r2->filters[wgt=1]->toLocal()
   *       => $r2->data->putEntities()
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
  function toGlobal(&$entities, YASS_Replica $from) {
  }
  
  /**
   * Modify a list of entities, converting global encodings to local encodings
   *
   * @param $entities array(YASS_Entity)
   */
  function toLocal(&$entities, YASS_Replica $to) {
  }

}
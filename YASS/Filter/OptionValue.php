<?php

require_once 'YASS/Filter.php';

/**
 * Convert option values to different formats (e.g. convert an activity_type_id to a name)
 *
 * Note: This uses the *local* option group mappings; this may constrain which replicas can/should use it
 */
class YASS_Filter_OptionValue extends YASS_Filter {

  /**
   *
   * @param $spec array; keys: 
   *  - entityType: string, the type of entity to which the filter applies
   *  - field: string, the incoming field name
   *  - localFormat: string, the format used on $replicaId ('value', 'name', 'label')
   *  - globalFormat: string, the format used on normalized replicas ('value', 'name', 'label')
   *  - group: string, the name of the optiongroup containing values/names/labels
   */
  function __construct($spec) {
    parent::__construct($spec);
    $this->spec = $spec;
  }
  
  function toLocal(&$entities, YASS_Replica $from, YASS_Replica $to) {
    $this->localMap = $this->getMap($this->spec['group'], $this->spec['globalFormat'], $this->spec['localFormat']);
    $field = $this->spec['field'];
    $entityType = $this->spec['entityType'];
    
    foreach ($entities as $entity) {
      if ($entity->entityType == $entityType && isset($entity->data[$field])) {
        if (!isset($this->localMap[ $entity->data[$field] ])) {
          // TODO consider auto-creating 
          throw new Exception(sprintf('Failed to map %s "%s" from global (%s) to local (%s) format',
            $field, $entity->data[$field], $this->spec['globalFormat'], $this->spec['localFormat']
          ));
        }
        $entity->data[$field] = $this->localMap[ $entity->data[$field] ];
      }
    }
  }
  
  function toGlobal(&$entities, YASS_Replica $from, YASS_Replica $to) {
    $this->globalMap = $this->getMap($this->spec['group'], $this->spec['localFormat'], $this->spec['globalFormat']);
    $field = $this->spec['field'];
    $entityType = $this->spec['entityType'];
    
    foreach ($entities as $entity) {
      if ($entity->entityType == $entityType && isset($entity->data[$field])) {
        if (!isset($this->globalMap[ $entity->data[$field] ])) {
          // TODO consider auto-creating 
          throw new Exception(sprintf('Failed to map %s "%s" from local (%s) to global (%s) format',
            $field, $entity->data[$field], $this->spec['localFormat'], $this->spec['globalFormat']
          ));
        }
        $entity->data[$field] = $this->globalMap[ $entity->data[$field] ];
      }
    }
  }
  
  /**
   * Build a mapping for option values
   */
  function getMap($group, $from, $to) {
    $q = db_query('SELECT cov.id, cov.name, cov.value, cov.label 
      FROM civicrm_option_value cov
      INNER JOIN civicrm_option_group cog on cov.option_group_id = cog.id
      WHERE cog.name = "%s"
    ', $group);
    $result = array();
    while ($row = db_fetch_object($q)) {
      $result[ $row->{$from} ] = $row->{$to};
    }
    return $result;
  }
}

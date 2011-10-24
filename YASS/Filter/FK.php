<?php

require_once 'YASS/Filter.php';

/**
 * Translate foreign keys between local and global ID
 *
 * This filter naively assumes that mappings are already setup. If an FK cannot be translated,
 * then it throws an exception.
 */
class YASS_Filter_FK extends YASS_Filter {

  /**
   *
   * @param $spec array; keys: 
   *  - entityType: string, the type of entity which contains the key
   *  - field: string, the column which stores the fk
   *  - fkType: string, the type of entity referenced by the key
   *  - mapper: YASS_GuidMapper
   */
  function __construct($spec) {
    parent::__construct($spec);
    $this->spec = $spec;
  }
  
  function toLocal(&$entities, YASS_Replica $from, YASS_Replica $to) {
    $mapper = $this->spec['mapper'];
    $field = $this->spec['field'];
    $entityType = $this->spec['entityType'];
    $fkType = $this->spec['fkType'];
    
    // TODO prefetch FK mappings en masse
    
    foreach ($entities as $entity) {
      if ($entity->entityType == $entityType && isset($entity->data[$field])) {
        list($mappedType, $lid) = $mapper->toLocal($entity->data[$field]);
        if ((!$lid) || ($mappedType != $fkType)) {
          throw new Exception(sprintf('Failed to map global=>local FK (replicaId=%s, entityType=%s, field=%s, fkType=%s, fkValue=%s)',
            $mapper->replica->id, $entityType, $field, $fkType, $entity->data[$field]
          ));
        }
        $entity->data[$field] = $lid;
      }
    }
  }
  
  function toGlobal(&$entities, YASS_Replica $from, YASS_Replica $to) {
    $mapper = $this->spec['mapper'];
    $field = $this->spec['field'];
    $entityType = $this->spec['entityType'];
    $fkType = $this->spec['fkType'];
    
    // TODO prefetch FK mappings en masse
    
    foreach ($entities as $entity) {
      if ($entity->entityType == $entityType && isset($entity->data[$field])) {
        $guid = $mapper->toGlobal($fkType, $entity->data[$field]);
        if (!$guid) {
          throw new Exception(sprintf('Failed to map local=>global FK (replicaId=%s, entityType=%s, field=%s, fkType=%s, fkValue=%s)',
            $mapper->replica->id, $entityType, $field, $fkType, $entity->data[$field]
          ));
        }
        $entity->data[$field] = $guid;
      }
    }
  }

}

<?php

/**
 * Translate between globally-unique ID's and replica-local (type,id) pairs.
 */
class YASS_GuidMapper {
  const NOT_FOUND = -1;

  /**
   * @var int
   */
  var $replicaId;
  
  /**
   * List mappings, indexed by guid
   *
   * @var array(guid => stdClass{yass_guidmap})
   */
  var $byGuid;
  
  /**
   * List of mappings, indexed by type and local id
   *
   * @var array(type => array(lid => stdClass{yass_guidmap})
   */
  var $byTypeId;
  
  /**
   *
   * @param $replicaId int, the replica for which we need global-local mappings
   */
  function __construct($replicaId) {
    $this->replicaId = $replicaId;
    $this->byGuid = array();
    $this->byTypeId = array();
  }
  
  /**
   * Translate a local (type,id) to a GUID
   *
   * ex: $guid = $mapper->toGlobal($type,$lid);
   *
   * @param $type string
   * @param $lid int
   * @return string or FALSE
   */
  function toGlobal($type, $lid) {
    if (!isset($this->byTypeId[$type][$lid])) {
      $this->loadLocalIds(array($type => array($lid)));
    }
    if ($this->byTypeId[$type][$lid] == self::NOT_FOUND) {
      return FALSE;
    } else {
      return $this->byTypeId[$type][$lid]->guid;
    }
  }
  
  /**
   * Translate a GUID to a local (type,id)
   *
   * ex: list($type,$lid) = $mapper->toLocal($guid);
   *
   * @param $guid string
   * @return array(0=>type, 1=>localId) or array(FALSE,FALSE)
   */
  function toLocal($guid) {
    if (! isset($this->byGuid[$guid])) {
      $this->loadGlobalIds(array($guid));
    }
    if ($this->byGuid[$guid] == self::NOT_FOUND) {
      return array(FALSE,FALSE);
    } else {
      return array($this->byGuid[$guid]->entity_type, $this->byGuid[$guid]->lid);
    }
  }
  
  /**
   * Pre-fetch the mappings for a list of GUIDs
   *
   * @param $guids array(entityGuid)
   * @return array(entityGuid => array('entity_type' => type, 'lid' => localId, 'guid' => entityGuid))
   */
  function loadGlobalIds($guids) {
    if (empty($guids)) {
      return array();
    }
    
    arms_util_include_api('query');
    $select = arms_util_query('{yass_guidmap}');
    $select->addSelects(array('entity_type', 'lid', 'guid'));
    $select->addWheref('replica_id = %d', $this->replicaId);
    $select->addWhere(arms_util_query_in('guid', $guids));
    $q = db_query($select->toSQL());
    while ($row = db_fetch_object($q)) {
      $this->byGuid[ $row->guid ] = $row;
      $this->byTypeId[ $row->entity_type ][ $row->lid ] = $row;
    }
    
    // Remember unmatched GUIDs
    foreach ($guids as $guid) {
      if (!isset($this->byGuid[$guid])) {
        $this->byGuid[$guid] = self::NOT_FOUND;
      }
    }
  }
  
  /**
   * Convert a list of local (type,ID)s to GUIDs.
   *
   * Unmapped items do not appear in the result set
   *
   * @param $localids array(type => array(localId))
   * @return array(entityGuid => array('entity_type' => type, 'lid' => localId, 'guid' => entityGuid))
   */
  function loadLocalIds($localids) {
    if (empty($localids)) {
      return array();
    }
    
    foreach ($localids as $type => $ids) {
      arms_util_include_api('query');
      $select = arms_util_query('{yass_guidmap}');
      $select->addSelects(array('entity_type', 'lid', 'guid'));
      $select->addWheref('replica_id = %d', $this->replicaId);
      $select->addWhere(arms_util_query_in('lid', $ids));
      $q = db_query($select->toSQL());
      while ($row = db_fetch_object($q)) {
        $this->byGuid[ $row->guid ] = $row;
        $this->byTypeId[ $row->entity_type ][ $row->lid ] = $row;
      }
      
      // Remember unmatched IDs
      foreach ($ids as $id) {
        if (!isset($this->byTypeId[$type][$id])) {
          $this->byTypeId[$type][$id] = self::NOT_FOUND;
        }
      }
    }
  }
  
  /**
   * Add or update mappings between GUIDs and local IDs
   *
   * @param $mappings array(type => array(localId => entityGuid))
   */
  function addMappings($mappings) {
    foreach ($mappings as $type => $idMappings) {
      foreach ($idMappings as $localId => $entityGuid) {
        $row = new stdClass();
        $row->entity_type = $type;
        $row->lid = $localId;
        $row->guid = $entityGuid;
        
        db_query('INSERT INTO {yass_guidmap} (replica_id,entity_type,lid,guid)
          VALUES (%d,"%s",%d,"%s")
          ON DUPLICATE KEY UPDATE guid = "%s"
        ', $this->replicaId, $row->entity_type, $row->lid, $row->guid, $row->guid);

        $this->byGuid[ $row->guid ] = $row;
        $this->byTypeId[ $row->entity_type ][ $row->lid ] = $row;
      }
    }
  }
}

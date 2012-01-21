<?php

/**
 * (Page callback)
 */
function yass_ui_pages_entity($replicaName, $entityId, $entityRev) {
  require_once 'YASS/Engine.php';
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  $master = YASS_Engine::singleton()->getReplicaByName('master');
  if (!$replica || !$master) {
    return drupal_not_found();
  }
  
  $revisions = array();
  $revisions['master'] = _yass_ui_pages_getArchiveRevisions($master, $entityId);
  $activeRevisions = _yass_ui_pages_getActiveRevisions($entityId);
  foreach ($activeRevisions as $activeReplicaName => $activeRevision) {
    $revisions[$activeReplicaName]['head'] = $activeRevision;
  }
  asort($revisions);
  
  if (!$entityRev) $entityRev = 'head';
  $entity = $revisions[$replicaName][$entityRev];
  if ($entity === FALSE) {
    return drupal_not_found();
  }
  $entityFilterLog = ($entityRev == 'head') ? _yass_ui_pages_getFilterLog($replica,$entityId) : FALSE;

  drupal_set_title(t('Entity - @entityType (@entityId)', array(
    '@entityType' => $entity->entityType ? $entity->entityType : 'Unknown',
    '@entityId' => $entity->entityGuid,
    '@uReplicaId' => $entity->version->replicaId,
    '@uTick' => $entity->version->tick,
  )));
  print theme('page', 
    theme('yass_ui_entity', $replica, $entity, $entityFilterLog, $revisions), 
    FALSE);
    
}

/**
 * Get an entity by revision
 *
 * @param $entityRev string, an optional revision-identifier (e.g. "uReplicaId:uTick", "123:456")
 * @return YASS_EntityRevision or FALSE
 */
function _yass_ui_pages_getRevision(YASS_Replica $replica, $entityId, $entityRev = FALSE) {
  
  if ($entityRev) {
    list ($uReplicaId, $uTick) = explode(':', $entityRev);
    $q = db_query('select entity_id, entity_type, is_extant, timestamp, u_replica_id, u_tick, data
      FROM {yass_archive}
      WHERE replica_id = %d
      AND entity_id = "%s"
      AND u_replica_id = %d
      AND u_tick = %d
      ', $replica->id, $entityId, $uReplicaId, $uTick);
    while ($row = db_fetch_array($q)) {
      return YASS_EntityRevision::createByArchive($row);
    }
    return FALSE;
  } else {
    $entities = $replica->data->getEntities(array($entityId));
    $syncStates = $replica->sync->getSyncStates(array($entityId));
    return YASS_EntityRevision::createByObject($entities[$entityId], $syncStates[$entityId]);
  }
}

/**
 * Get a list of available revisions
 *
 * @return array($revCode => YASS_EntityRevision)
 */
function _yass_ui_pages_getArchiveRevisions(YASS_Replica $replica, $entityId) {
  require_once 'YASS/EntityRevision.php';
  $q = db_query('select replica_id, entity_id, entity_type, is_extant, timestamp, u_replica_id, u_tick, data
    FROM {yass_archive}
    WHERE replica_id = %d
    AND entity_id = "%s"
    ORDER BY timestamp DESC
    ', $replica->id, $entityId);
  $revisions = array();
  while ($row = db_fetch_array($q)) {
    $revCode = $row['u_replica_id'] . ':' . $row['u_tick'];
    $revisions[$revCode] = YASS_EntityRevision::createByArchive($row);
  }
  
  return $revisions;
}

/**
 * Get the current revision from every replica
 *
 * @return array($replicaName => YASS_EntityRevision)
 */
function _yass_ui_pages_getActiveRevisions($entityId) {
  require_once 'YASS/EntityRevision.php';
  require_once 'YASS/Engine.php';
  $ctx = new YASS_Context(array(
    'disableAccessControl' => TRUE,
  ));
  
  $replicas = YASS_Engine::singleton()->getActiveReplicas();
  $revisions = array();
  foreach ($replicas as $replica) {
    $entities = $replica->data->getEntities(array($entityId));
    if ($entities[$entityId]->exists) {
      $syncStates = $replica->sync->getSyncStates(array($entityId));
      $revisions[$replica->name] = YASS_EntityRevision::createByObject($entities[$entityId], $syncStates[$entityId]->modified);
    } else {
      $revisions[$replica->name] = YASS_EntityRevision::createByObject($entities[$entityId], new YASS_Version('?','?'));
    }
  }
  return $revisions;
}

/**
 * Get a list of the filtering steps which produced the entity
 */
function _yass_ui_pages_getFilterLog(YASS_Replica $replica, $entityId) {  
  $ctx = new YASS_Context(array(
    'disableAccessControl' => TRUE,
    'filterListener' => new _yass_ui_pages_FilterListener(),
  ));
  $replica->data->getEntities(array($entityId));
  return YASS_Context::get('filterListener')->log;
}

require_once 'YASS/IFilterListener.php';
class _yass_ui_pages_FilterListener extends YASS_IFilterListener {
  var $log;
  
  function __construct() {
    $this->log = array();
  }
  
  function beginToGlobal(&$entities, YASS_Replica $replica) {
    foreach ($entities as $entity) {
      $this->log[] = array($replica->name, get_class($replica->data).' ::getEntities()' , krumo_ob($replica->spec), krumo_ob($entity));
    }
  }
  function onToGlobal(&$entities, YASS_Replica $replica, YASS_Filter $filter) {
    foreach ($entities as $entity) {
      $this->log[] = array($replica->name, get_class($filter).' ::toGlobal()', krumo_ob($filter->spec), krumo_ob($entity));
    }
  }
}

<?php

/**
 * (Page callback)
 */
function yass_ui_pages_entity($replicaName, $entityId, $entityRev) {
  require_once 'YASS/Engine.php';
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return drupal_not_found();
  }
  
  $entity = _yass_ui_pages_getRevision($replica, $entityId, $entityRev);
  if ($entity === FALSE) {
    return drupal_not_found();
  }
  $revisions = _yass_ui_pages_getRevisions($replica, $entityId);

  drupal_set_title(t('Entity - @entityType (@entityId @ @uReplicaId:@uTick)', array(
    '@entityType' => $entity->entityType ? $entity->entityType : 'Unknown',
    '@entityId' => $entity->entityGuid,
    '@uReplicaId' => $entity->version->replicaId,
    '@uTick' => $entity->version->tick,
  )));
  return theme('yass_ui_entity', $replica, $entity, $revisions);
}

/**
 * Get an entity by revision
 *
 * @param $entityRev string, an optional revision-identifier (e.g. "uReplicaId:uTick", "123:456")
 * @return array(...) or FALSE
 */
function _yass_ui_pages_getRevision(YASS_Replica $replica, $entityId, $entityRev = FALSE) {
  require_once 'YASS/EntityRevision.php';
  $ctx = new YASS_Context(array(
    'disableAccessControl' => TRUE,
  ));
  
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
 */
function _yass_ui_pages_getRevisions(YASS_Replica $replica, $entityId) {
  require_once 'YASS/EntityRevision.php';
  $q = db_query('select entity_id, entity_type, is_extant, timestamp, u_replica_id, u_tick, data
    FROM {yass_archive}
    WHERE replica_id = %d
    AND entity_id = "%s"
    ORDER BY timestamp DESC
    ', $replica->id, $entityId);
  $revisions = array();
  while ($row = db_fetch_array($q)) {
    $revisions[] = YASS_EntityRevision::createByArchive($row);
  }
  return $revisions;
}

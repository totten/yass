<?php

/**
 * (Drush callback)
 *
 * Print details
 */
function _yass_replica_arms_drush_contact($contactGuid) {
  require_once 'YASS/Engine.php';
  $replica = YASS_Engine::singleton()->getReplicaByName('arms');
  if (!$replica) {
    printf("Unknown replica: %s\n", $replicaName);
    exit(1);
  }
  $guids = _yass_replica_arms_drush_findContactGuids($replica, $contactGuid);
  ksort($guids);
  $entities = $replica->data->getEntities($guids);
  $syncStates = $replica->sync->getSyncStates($guids);
  foreach ($syncStates as $syncState) {
    $entities[$syncState->entityGuid]->syncState = array(
      'modified' => (array) $syncState->modified,
      'created' => (array) $syncState->created,
    );
  }
   _yass_replica_arms_drush_print($entities);
}

/**
 * Find a list of GUIDs related to a contact.
 *
 * Only works with the *local* replica
 *
 * @return array($entityGuid)
 */
function _yass_replica_arms_drush_findContactGuids(YASS_Replica $replica, $contactGuid) {
  list ($type, $contactId) = $replica->mapper->toLocal($contactGuid);
  if ($type != 'civicrm_contact') {
    return array();
  }
  
  $result = array();
  $result[] = $contactGuid;
  
  foreach (array(
    'civicrm_address' => 'contact_id',
    'civicrm_phone' => 'contact_id',
    'civicrm_email' => 'contact_id',
  ) as $entityType => $entityColumn) {
    $sql = strtr('
      SELECT map.guid
      FROM @entityType entity
      INNER JOIN {yass_guidmap} map on map.entity_type = "@entityType" AND map.lid = entity.id
      WHERE entity.@entityColumn = @contactId
    ', array(
      '@entityType' => $entityType,
      '@entityColumn' => $entityColumn,
      '@contactId' => $contactId,
    ));
    $q = db_query($sql);
    while ($row = db_fetch_array($q)) {
      $result[] = $row['guid'];
    }
  }
  return $result;
}

function _yass_replica_arms_drush_print($entities) {
  foreach ($entities as $entity) {
    $flat = arms_util_implode_tree('/', $entity);
    ksort($flat);
    foreach ($flat as $key => $value) {
      if ($value !== '' && $value !== NULL) {
        printf("%-15s %-36s %-36s %s\n", $entity->entityType, $entity->entityGuid, $key, $value);
      }
    }
  }
}

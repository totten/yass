<?php

require_once 'YASS/Engine.php';
require_once 'YASS/Proxy.php';

/**
 * (Services callback)
 */
function yass_service_getReplicas() {
  arms_util_include_api('array');
  $replicas = YASS_Engine::singleton()->getReplicas();
  return arms_util_array_collect($replicas, 'spec');
}

/**
 * (Services callback)
 */
function yass_service_getEntityTypes($replicaDesc) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  $result = $replica->schema->getEntityTypes();
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_getEntities($replicaDesc, $entityGuids) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  $result = $replica->data->getEntities($entityGuids);
  YASS_Proxy::encodeAllInplace('YASS_Entity', $result);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_putEntities($replicaDesc, $entities) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  YASS_Proxy::decodeAllInplace('YASS_Entity', $entities);
  $replica->data->putEntities($entities);
}

/**
 * (Services callback)
 */
function yass_service_validateGuids($replicaDesc) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  module_invoke_all('yass_replica', array('op' => 'validateGuids', 'replica' => &$replica));
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_getLastSeenVersions($replicaDesc) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  $result = $replica->sync->getLastSeenVersions();
  YASS_Proxy::encodeAllInplace('YASS_Version', $result);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_markSeens($replicaDesc, $versions) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  YASS_Proxy::decodeAllInplace('YASS_Version', $versions);
  $replica->sync->markSeens($versions);
}

/**
 * (Services callback)
 */
function yass_service_getModifieds($replicaDesc, $lastSeenVersions) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  YASS_Proxy::decodeAllInplace('YASS_Version', $lastSeenVersions);
  $result = $replica->sync->getModifieds($lastSeenVersions);
  YASS_Proxy::encodeAllInplace('YASS_SyncState', $result);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_getSyncStates($replicaDesc, $entityGuids) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  $result = $replica->sync->getSyncStates($entityGuids);
  YASS_Proxy::encodeAllInplace('YASS_SyncState', $result);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_setSyncStates($replicaDesc, $versions) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  YASS_Proxy::decodeAllInplace('YASS_Version', $versions);
  $replica->sync->setSyncStates($versions);
}

/**
 * (Services callback)
 */
function yass_service_updateAllVersions($replicaDesc) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  $replica->sync->updateAllVersions();
}

/**
 * (Services callback)
 */
function yass_service_destroy($replicaDesc) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  $replica->sync->destroy();
}

/**
 * (Services callback)
 *
 * @param $guids array(entityGuid)
 * @return array(entityGuid => stdClass('entity_type' => type, 'lid' => localId, 'guid' => entityGuid))
 */
function yass_service_loadGlobalIds($replicaDesc, $guids) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  $result = $replica->mapper->loadGlobalIds($guids);
  return $result;
}

/**
 * (Services callback)
 *
 * @param $localids array(type => array(localId))
 * @return array(entityGuid => stdClass('entity_type' => type, 'lid' => localId, 'guid' => entityGuid))
 */
function yass_service_loadLocalIds($replicaDesc, $localids) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  $result = $replica->mapper->loadLocalIds($localids);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_addMappings($replicaDesc, $mappings) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  $result = $replica->mapper->addMappings($mappings);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_destroyMappings($replicaDesc) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  $result = $replica->mapper->destroy();
  return $result;
}

/**
 * (Services callback)
 *
function yass_service_X($replicaDesc, $X) {
  $replica = _yass_service_getReplica($replicaDesc);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaDesc)));
  }
  YASS_Proxy::decodeAllInplace('X', $X);
  $result = $replica->X();
  YASS_Proxy::encodeAllInplace('X', $result);  
  return $result;
}
*/

// --------------- Helpers ---------------

/**
 * Generate a non-recoverable error
 */
function _yass_service_error($message = 'Unhandled error') {
  throw new Exception($message);
}

function _yass_service_getReplica($replicaDesc) {
  list ($replicaName, $effectiveReplicaId) = $replicaDesc;
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (is_object($replica)) {
    YASS_Engine::singleton()->setEffectiveReplicaId($replica, $effectiveReplicaId);
  }
  
  return $replica;
}

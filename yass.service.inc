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
function yass_service_getEntityTypes($replicaName) {
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaName)));
  }
  $result = $replica->schema->getEntityTypes();
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_getEntities($replicaName, $entityGuids) {
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaName)));
  }
  $result = $replica->data->getEntities($entityGuids);
  YASS_Proxy::encodeAllInplace('YASS_Entity', $result);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_putEntities($replicaName, $entities) {
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaName)));
  }
  YASS_Proxy::decodeAllInplace('YASS_Entity', $entities);
  $replica->data->putEntities($entities);
}

/**
 * (Services callback)
 */
function yass_service_validateGuids($replicaName) {
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaName)));
  }
  module_invoke_all('yass_replica', array('op' => 'validateGuids', 'replica' => &$replica));
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_getLastSeenVersions($replicaName) {
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaName)));
  }
  $result = $replica->sync->getLastSeenVersions();
  YASS_Proxy::encodeAllInplace('YASS_Version', $result);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_markSeens($replicaName, $versions) {
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaName)));
  }
  YASS_Proxy::decodeAllInplace('YASS_Version', $versions);
  $replica->sync->markSeens($versions);
}

/**
 * (Services callback)
 */
function yass_service_getModifieds($replicaName, $lastSeenVersions) {
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaName)));
  }
  YASS_Proxy::decodeAllInplace('YASS_Version', $lastSeenVersions);
  $result = $replica->sync->getModifieds($lastSeenVersions);
  YASS_Proxy::encodeAllInplace('YASS_SyncState', $result);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_getSyncStates($replicaName, $entityGuids) {
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaName)));
  }
  $result = $replica->sync->getSyncStates($entityGuids);
  YASS_Proxy::encodeAllInplace('YASS_SyncState', $result);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_setSyncStates($replicaName, $versions) {
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaName)));
  }
  YASS_Proxy::decodeAllInplace('YASS_Version', $versions);
  $replica->sync->setSyncStates($versions);
}

/**
 * (Services callback)
 */
function yass_service_updateAllVersions($replicaName) {
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaName)));
  }
  $replica->sync->updateAllVersions();
}

/**
 * (Services callback)
 */
function yass_service_destroy($replicaName) {
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaName)));
  }
  $replica->sync->destroy();
}

/**
 * (Services callback)
 *
function X($replicaName) {
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $replicaName)));
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

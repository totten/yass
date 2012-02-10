<?php

/*
 +--------------------------------------------------------------------+
 | YASS                                                               |
 +--------------------------------------------------------------------+
 | Copyright ARMS Software LLC (c) 2011-2012                          |
 +--------------------------------------------------------------------+
 | This file is a part of YASS.                                       |
 |                                                                    |
 | YASS is free software; you can copy, modify, and distribute it     |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007.                                       |
 |                                                                    |
 | YASS is distributed in the hope that it will be useful, but        |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | Additional permissions may be granted. See LICENSE.txt for         |
 | details.                                                           |
 +--------------------------------------------------------------------+
*/

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
function yass_service_getEntityTypes($contextVars) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $contextVars)));
  }
  $result = $replica->schema->getEntityTypes();
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_getEntities($contextVars, $entityGuids) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  $result = $replica->data->getEntities($entityGuids);
  YASS_Proxy::encodeAllInplace('YASS_Entity', $result);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_putEntities($contextVars, $entities) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  YASS_Proxy::decodeAllInplace('YASS_Entity', $entities);
  
  require_once 'YASS/Addendum.php';
  $addendum = new YASS_Addendum($replica);
  YASS_Context::push(array(
    'addendum' => $addendum,
  ));
  
  try {
    $replica->data->putEntities($entities);
    YASS_Context::pop();
    YASS_Proxy::encodeAllInplace('YASS_Addendum', $addendum);
    return $addendum;
  } catch (Exception $e) {
    YASS_Context::pop();
    throw $e;
  }
}

/**
 * (Services callback)
 */
function yass_service_validateGuids($contextVars) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  module_invoke_all('yass_replica', array('op' => 'validateGuids', 'replica' => &$replica));
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_getLastSeenVersions($contextVars) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  $result = $replica->sync->getLastSeenVersions();
  YASS_Proxy::encodeAllInplace('YASS_Version', $result);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_markSeens($contextVars, $versions) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  YASS_Proxy::decodeAllInplace('YASS_Version', $versions);
  $replica->sync->markSeens($versions);
}

/**
 * (Services callback)
 */
function yass_service_getModifieds($contextVars, $lastSeenVersions) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  YASS_Proxy::decodeAllInplace('YASS_Version', $lastSeenVersions);
  $result = $replica->sync->getModifieds($lastSeenVersions);
  YASS_Proxy::encodeAllInplace('YASS_SyncState', $result);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_getSyncStates($contextVars, $entityGuids) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  $result = $replica->sync->getSyncStates($entityGuids);
  YASS_Proxy::encodeAllInplace('YASS_SyncState', $result);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_setSyncStates($contextVars, $versions) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  YASS_Proxy::decodeAllInplace('YASS_Version', $versions);
  $replica->sync->setSyncStates($versions);
}

/**
 * (Services callback)
 */
function yass_service_updateAllVersions($contextVars) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  $replica->sync->updateAllVersions();
}

/**
 * (Services callback)
 */
function yass_service_destroy($contextVars) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  $replica->sync->destroy();
}

/**
 * (Services callback)
 *
 * @param $guids array(entityGuid)
 * @return array(entityGuid => stdClass('entity_type' => type, 'lid' => localId, 'guid' => entityGuid))
 */
function yass_service_loadGlobalIds($contextVars, $guids) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
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
function yass_service_loadLocalIds($contextVars, $localids) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  $result = $replica->mapper->loadLocalIds($localids);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_addMappings($contextVars, $mappings) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  $result = $replica->mapper->addMappings($mappings);
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_destroyMappings($contextVars) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  $result = $replica->mapper->destroy();
  return $result;
}

/**
 * (Services callback)
 */
function yass_service_tick($contextVars) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
  }
  $result = $replica->sync->tick();
  return $result;
}

/**
 * (Services callback)
 *
function yass_service_X($contextVars, $X) {
  list ($ctx, $replica) = YASS_Proxy::decodeContext($contextVars);
  if (!$replica) {
    return _yass_service_error(t('Failed to locate replica (!name)', array('!name' => $ctx)));
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

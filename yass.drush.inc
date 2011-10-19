<?php

/** 
 * (Drush callback)
 * 
 * List available replicas
 */
function _yass_drush_ls() {
  yass_arms_clear(); // Load the latest replica metadata
  
  require_once 'YASS/Engine.php';
  $replicas = YASS_Engine::singleton()->getReplicas();
  printf("%-6s %-20s %-5s %-15s %-15s\n", "ID", "NAME", "ACTIVE", "DATASTORE", "SYNCSTORE");
  foreach ($replicas as $replica) {
    printf("%-6d %-20s %-5s %-15s %-15s\n", 
      $replica->spec['id'],
      $replica->spec['name'],
      $replica->spec['is_active'] ? 'Y' : 'N',
      $replica->spec['datastore'],
      $replica->spec['syncstore']
    );
  }
}

/**
 * (Drush callback)
 *
 * Remove information about a replica
 */
function _yass_drush_rm($replicaName) {
  require_once 'YASS/Engine.php';
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    printf("Unknown replica: %s\n", $replicaName);
    exit(1);
  }
  YASS_Engine::singleton()->destroyReplica($replica);
}

/**
 * (Drush callback)
 */
function _yass_drush_join($replicaName) {
  require_once 'YASS/Engine.php';
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    printf("Unknown replica: %s\n", $replicaName);
    exit(1);
  }
  YASS_Engine::singleton()->join($replica, self::getReplicaByName('master'));
}

/**
 * (Drush callback)
 */
function _yass_drush_rejoin($replicaName) {
  require_once 'YASS/Engine.php';
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    printf("Unknown replica: %s\n", $replicaName);
    exit(1);
  }
  YASS_Engine::singleton()->rejoin($replica, self::getReplicaByName('master'));
}


/**
 * (Drush callback)
 */
function _yass_drush_reset($replicaName) {
  require_once 'YASS/Engine.php';
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    printf("Unknown replica: %s\n", $replicaName);
    exit(1);
  }
  YASS_Engine::singleton()->reset($replica, self::getReplicaByName('master'));
}

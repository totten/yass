<?php

/** 
 * (Drush callback)
 * 
 * List available replicas
 */
function _yass_drush_ls() {
  yass_arms_clear(); // Load the latest replica metadata
  
  require_once 'YASS/Engine.php';
  $metadata = YASS_Engine::singleton()->getReplicaMetadata();
  printf("%-3s %-20s %-5s %-15s %-15s\n", "ID", "NAME", "ACTIVE", "DATASTORE", "SYNCSTORE");
  foreach ($metadata as $replicaSpec) {
    printf("%-3d %-20s %-5s %-15s %-15s\n", 
      $replicaSpec['id'],
      $replicaSpec['name'],
      $replicaSpec['is_active'] ? 'Y' : 'N',
      $replicaSpec['datastore'],
      $replicaSpec['syncstore']
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

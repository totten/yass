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
  printf("%-6s %-25s %-5s %-15s %-15s\n", "ID", "NAME", "ACTIVE", "DATASTORE", "SYNCSTORE");
  foreach ($replicas as $replica) {
    printf("%-6d %-25s %-5s %-15s %-15s\n", 
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
  YASS_Engine::singleton()->join($replica, YASS_Engine::singleton()->getReplicaByName('master'));
}

/**
 * (Drush callback)
 */
function _yass_drush_hard_push($srcName, $destName) {
  require_once 'YASS/Engine.php';
  $src = YASS_Engine::singleton()->getReplicaByName($srcName);
  if (!$src) {
    printf("Unknown src replica: %s\n", $srcName);
    exit(1);
  }
  $dest = YASS_Engine::singleton()->getReplicaByName($destName);
  if (!$dest) {
    printf("Unknown dest replica: %s\n", $destName);
    exit(1);
  }
  YASS_Engine::singleton()->hardPush($src, $dest);
}

/**
 * (Drush callback)
 */
function _yass_drush_hard_tick($replicaName) {
  require_once 'YASS/Engine.php';
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    printf("Unknown replica: %s\n", $replicaName);
    exit(1);
  }
  YASS_Engine::singleton()->hardTick($replica);
}

/** 
 * (Drush callback)
 * 
 * Print detailed information about a replica
 */
function _yass_drush_info($replicaName) {
  require_once 'YASS/Engine.php';
  $replica = YASS_Engine::singleton()->getReplicaByName($replicaName);
  if (!$replica) {
    printf("Unknown replica: %s\n", $replicaName);
    exit(1);
  }
  printf("ID: %d\n", $replica->id);
  printf("Name: %s\n", $replica->name);
  printf("Spec: %s\n", print_r($replica->spec, TRUE));
  printf("Filters: %s\n", print_r($replica->filters, TRUE));
}

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


/**
 * (Drush callback)
 */
function _yass_drush_export($srcName, $effDestName = FALSE) {
  require_once 'YASS/Engine.php';
  $src = YASS_Engine::singleton()->getReplicaByName($srcName);
  if (!$src) {
    printf("Unknown src replica: %s\n", $srcName);
    exit(1);
  }
  
  if ($effDestName) {
    $dest = YASS_Engine::singleton()->getReplicaByName($effDestName);
    if (!$dest) {
      printf("Unknown dest replica: %s\n", $effDestName);
      exit(1);
    }
    $console = YASS_Replica::create(array(
      'type' => 'Console',
      'id' => $dest->id,
      'name' => $dest->name,
    ));
  } else {
    $console = YASS_Replica::create(array(
      'type' => 'Console',
      'id' => -1,
      'name' => 'console',
    ));
  }
  YASS_Engine::singleton()->hardPush($src, $console);
}

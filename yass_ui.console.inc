<?php

/**
 * (Form API)
 */
function yass_ui_console(&$form_state) {
  require_once 'YASS/Engine.php';
  $replicas = YASS_Engine::singleton()->getActiveReplicas();
  $lastSeens = _yass_ui_console_getLastSeenVersions($replicas);
  
  // Note: In standard star-topology with bidir sync, "dest_replica_id" is master and 'src_replica_id' is client
  require_once 'YASS/SyncStatus.php';
  $lastRuns = YASS_SyncStatus::find($replicas, YASS_Engine::singleton()->getReplicaByName('master'));
  
  $form = array(
    '#tree' => TRUE,
  );
  
  $form['syncnow'] = array(
    '#type' => 'submit',
    '#value' => t('Sync Now'),
  );
  $form['status'] = array(
    '#type' => 'markup',
    '#value' => theme('yass_ui_console_status', $replicas, $lastSeens, $lastRuns),
  );
  return $form;
}

/**
 * @return array(replicaId => array(replicaId=>YASS_Version))
 */
function _yass_ui_console_getLastSeenVersions($replicas) {
  $result = array();
  foreach ($replicas as $replica) {
    $result[$replica->id] = $replica->sync->getLastSeenVersions();
  }
  return $result;
}

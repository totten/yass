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


/**
 * (Form API - Submit handler)
 */ 
function yass_ui_console_submit(&$form, &$form_state) {
  $batch = array(
    'title' => t('Synchronize all active replicas'),
    'file' => drupal_get_path('module', 'yass_ui') . '/yass_ui.console.inc',
    'progress_message' => t('Completed job @current of @total'),
    'operations' => array(),
    'finished' => '_yass_ui_console_finish',
  );
  
  require_once 'YASS/Engine.php';
  $replicas = YASS_Engine::singleton()->getActiveReplicas();
  $master = YASS_Engine::singleton()->getReplicaByName('master'); // FIXME
  
  // FIXME: DRY: YASS_Engine::syncAll() duplicates this process in a manner which doesn't require Drupal's Batch API
  for ($i = 0; $i<2; $i++) {
    foreach ($replicas as $replica) {
      if ($replica->id == $master->id) continue;
      $batch['operations'][] = array('_yass_ui_console_runSync', array($replica->id, $master->id));
    }
  }
  
  if (!empty($batch['operations'])) {
    batch_set($batch);
  }
}

/**
 * (Batch API - Operation handler)
 */
function _yass_ui_console_runSync($srcId, $destId, &$context) {
  require_once 'YASS/Engine.php';
  $src = YASS_Engine::singleton()->getReplicaById($srcId);
  $dest = YASS_Engine::singleton()->getReplicaById($destId);
  
  require_once 'YASS/ConflictResolver/Auto.php';
  $conflictResolver = new YASS_ConflictResolver_Auto(); // FIXME: Need a better policy
  
  YASS_Engine::singleton()->bidir($src, $dest, $conflictResolver);
}

/**
 * (Batch API - Finish handler)
 */
function _yass_ui_console_finish($success, $results, $operations) {
  if ($success) {
    drupal_set_message(t('Finished synchronization (@time)', array(
      '@time' => format_date(arms_util_time(), 'large'),
    )));
  } else {
    drupal_set_message(t('Failed to finish synchronization.'), 'error');
  }
}

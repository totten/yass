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

define(YASS_UI_CONSOLE_PAGE_SIZE, 50);

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
  
  require_once 'YASS/LogTable.php';
  $form['log'] = array(
    '#type' => 'markup',
    '#value' => theme('yass_ui_console_log', pager_query(
      YASS_LogTable::createFindRecentQuery(),
      YASS_UI_CONSOLE_PAGE_SIZE
    )),
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
  
  YASS_Engine::singleton()->bidir($src, $dest, YASS_Engine::singleton()->getReplicaByName('master'), $conflictResolver);
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

/**
 * (Form API)
 */
function yass_ui_console_hardpush(&$form_state) {
  require_once 'YASS/Engine.php';
  $replicas = YASS_Engine::singleton()->getActiveReplicas();
  $replicaNames = arms_util_array_combine_properties($replicas, 'id', 'name');
  
  $form = array(
    '#tree' => FALSE,
  );
  
  $form['what'] = array(
    '#type' => 'fieldset',
    '#title' => t('Hard Push: What It Does'),
    '#value' =>  '<p>' . t('A hard push re-transfers all data (and related synchronization metadata) from one replica to another -- overwriting any discrepancies in the destination.') . '</p>',
  );
  $form['whento'] = array(
    '#type' => 'fieldset',
    '#title' => t('Hard Push: When to Use It'),
    '#value' =>  '<p>' . t('Use this when you have one corrupted replica that should be reset using valid data from another replica. For example, if you plan to reorganize certain custom data fields on a client replica, then the client could become corrupt. As the part of the reorganization, you might fix the corruption by hard-pushing from the master to the reorganized client.') . '</p>'
  );
  $form['example'] = array(
    '#type' => 'fieldset',
    '#title' => t('Hard Push: Example'),
    '#value' =>  '<p>' . t('Suppose you plan to reorganize important custom data fields on <code>client.example.com</code>. The ideal steps would be:').'</p>'
      .theme('item_list', array(
        t('Disable customer access (e.g. set the sites in maintenance mode)'),
        t('Disable automatic data synchronization'),
        t('Backup all databases'),
        t('Update the custom data fields'),
        t('Perform a hard-push from <code>master</code> to <code>client.example.com</code>'),
        t('Re-enable automatic data synchronization'),
        t('Re-enable customer access'),
      )),
  );
  $form['instruct'] = array(
    '#type' => 'fieldset',
    '#title' => t('Hard Push: Instructions'),
    '#value' => theme('item_list', array(
      '<em>'.t('(Note: The current implementation is too inefficient to run through the web interface. It must be run via shell.)').'</em>',
      t('Login to the web-server shell and acquire administrative credentials'),
      t('Enter: <code>@cmd</code>', array(
        '@cmd' => 'cd ' . $_SERVER['DOCUMENT_ROOT'],
      )),
      t('Enter: <code>@cmd</code>', array(
        '@cmd' => './drush.php -l ' . $_SERVER['HTTP_HOST'] . ' yass-hard-push <pristine-replica-name> <corrupt-replica-name>',
      )),
    )),
  );

  /*
  $form['options'] = array(
    '#type' => 'fieldset',
    '#title' => t('Hard Push'),
  );
  $form['options']['src_replica'] = array(
    '#type' => 'select',
    '#title' => t('Source'),
    '#options' => $replicaNames,
  );
  $form['options']['dest_replica'] = array(
    '#type' => 'select',
    '#title' => t('Destination'),
    '#options' => $replicaNames,
  );
  $form['options']['push'] = array(
    '#type' => 'submit',
    '#value' => t('Push'),
  );
  */
  return $form;
}


/**
 * (Form API)
 */
function yass_ui_console_hardtick(&$form_state) {
  require_once 'YASS/Engine.php';
  $replicas = YASS_Engine::singleton()->getActiveReplicas();
  $replicaNames = arms_util_array_combine_properties($replicas, 'id', 'name');
  
  $form = array(
    '#tree' => FALSE,
  );
  
  $form['what'] = array(
    '#type' => 'fieldset',
    '#title' => t('Hard Tick: What It Does'),
    '#value' =>  '<p>' . t('A hard tick flags all data on one replica as newly modified. This will force the data to propagate to all other replicas.') . '</p>',
  );
  $form['whento'] = array(
    '#type' => 'fieldset',
    '#title' => t('Hard Tick: When to Use It'),
    '#value' =>  '<p>' . t('Use this when you have one valid replica and a series of corrupt replicas.') . '</p>'
  );
  /*
  $form['example'] = array(
    '#type' => 'fieldset',
    '#title' => t('Hard Push: Example'),
    '#value' =>  '<p>' . t('Suppose you plan to reorganize important custom data fields on <code>client.example.com</code>. The ideal steps would be:').'</p>'
      .theme('item_list', array(
        t('Disable customer access (e.g. set the sites in maintenance mode)'),
        t('Disable automatic data synchronization'),
        t('Backup all databases'),
        t('Update the custom data fields'),
        t('Perform a hard-push from <code>master</code> to <code>client.example.com</code>'),
        t('Re-enable automatic data synchronization'),
        t('Re-enable customer access'),
      )),
  );
  */
  $form['instruct'] = array(
    '#type' => 'fieldset',
    '#title' => t('Hard Tick: Instructions'),
    '#value' => theme('item_list', array(
      '<em>'.t('(Note: The current implementation is too inefficient to run through the web interface. It must be run via shell.)').'</em>',
      t('Login to the web-server shell and acquire administrative credentials'),
      t('Enter: <code>@cmd</code>', array(
        '@cmd' => 'cd ' . $_SERVER['DOCUMENT_ROOT'],
      )),
      t('Enter: <code>@cmd</code>', array(
        '@cmd' => './drush.php -l ' . $_SERVER['HTTP_HOST'] . ' yass-hard-tick <pristine-replica-name>',
      )),
      t('Enter: <code>@cmd</code>', array(
        '@cmd' => './drush.php -l ' . $_SERVER['HTTP_HOST'] . ' yass-master-sync',
      )),
    )),
  );

  /*
  $form['options'] = array(
    '#type' => 'fieldset',
    '#title' => t('Hard Tick'),
  );
  $form['options']['replica'] = array(
    '#type' => 'select',
    '#title' => t('Pristine Replica'),
    '#options' => $replicaNames,
  );
  $form['options']['tick'] = array(
    '#type' => 'submit',
    '#value' => t('Tick'),
  );
  */
  return $form;
}


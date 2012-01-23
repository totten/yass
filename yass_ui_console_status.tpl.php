<h2>Sync Status</h2>

<?
require_once 'YASS/Engine.php';
$masterReplica = YASS_Engine::singleton()->getReplicaByName('master');

$headers = array(t('Replica'), t('ID'), t('Last Run (Start)'), t('Last Run (End)'), t('Latest Revisions'));
$rows = array();
foreach ($replicas as $replica) {
  $row = array();
  $row[] = array('valign' => 'top', 'data' => $replica->name);
  $row[] = array('valign' => 'top', 'data' => $replica->id);
  $row[] = array('valign' => 'top', 'data' => _yass_ui_console_status_formatDate($lastRuns[$replica->id][$masterReplica->id]['start_ts']));
  $row[] = array('valign' => 'top', 'data' => _yass_ui_console_status_formatDate($lastRuns[$replica->id][$masterReplica->id]['end_ts']));
  
  $versions = array();
  foreach ($lastSeens[$replica->id] as $version) {
    $versions[] = $version->replicaId . ':' . $version->tick;
  }
  $row[] = array('valign' => 'top', 'data' => implode(', ', $versions));

  $rows[] = $row;
}

print theme('table', $headers, $rows);

function _yass_ui_console_status_formatDate($date) {
  if ($date !== NULL) {
    return format_date($date, 'custom', 'Y-m-d h:i:s a');
  } else {
    return '';
  }
}

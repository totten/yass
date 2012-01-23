<h2>Sync Status</h2>

<?
$headers = array(t('Replica'), t('ID'), t('Latest Sync (Completion Time)'), t('Latest Sync (Revisions)'));
$rows = array();
foreach ($replicas as $replica) {
  $row = array();
  $row[] = array('valign' => 'top', 'data' => $replica->name);
  $row[] = array('valign' => 'top', 'data' => $replica->id);
  $row[] = array('valign' => 'top', 'data' => t('TODO'));
  
  $versions = array();
  foreach ($lastSeens[$replica->id] as $version) {
    $versions[] = $version->replicaId . ':' . $version->tick;
  }
  $row[] = array('valign' => 'top', 'data' => implode(', ', $versions));

  $rows[] = $row;
}

print theme('table', $headers, $rows);

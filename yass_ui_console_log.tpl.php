<h2>Sync Log</h2>
<?php

$headers = array(t('Time'), t('From'), t('To'), t('Entity Type'), t('Entity ID'), t('Entity Revision'));
$rows = array();
while ($log = db_fetch_array($log_pager)) {
  $row = array();
  $row[] = format_date($log['timestamp'], 'custom', 'Y-m-d h:i:s a T');
  $row[] = t('@name (@id)', array(
    '@name' => $log['from_replica_name'],
    '@id' => $log['from_replica_id'],
  ));
  $row[] = t('@name (@id)', array(
    '@name' => $log['to_replica_name'],
    '@id' => $log['to_replica_id'],
  ));
  $row[] = check_plain($log['entity_type']);
  $row[] = l(
    check_plain($log['entity_id']),
    sprintf('yass/entity/master/%s', $log['entity_id'])
  );
  
  $row[] = l(
    check_plain($log['u_replica_id'] . ':' . $log['u_tick']),
    sprintf('yass/entity/master/%s/%d:%d', $log['entity_id'], $log['u_replica_id'], $log['u_tick'])
  );
  
  $rows[] = $row;
}

echo theme('table', $headers, $rows);
echo theme('pager', array(), YASS_UI_CONSOLE_PAGE_SIZE);
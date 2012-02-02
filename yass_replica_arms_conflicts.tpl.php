<?
// @param $conflicts

$headers = array(t('Contact'), t('Date'));
$rows = array();
foreach ($conflicts as $conflict) {
  $row = array();
  $row[] = l(check_plain($conflict['display_name']), 'civicrm/contact/view', array(
    'query' => array(
      'action' => 'browse',
      'reset' => 1,
      'cid' => $conflict['contact_id'],
      'selectedChild' => 'log',
    ),
  ));
  $row[] = format_date($conflict['timestamp']);
  $rows[] = $row;
}
echo theme('table', $headers, $rows);

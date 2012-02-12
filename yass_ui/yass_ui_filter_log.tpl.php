<?php

$headers = array('Step', 'Spec', 'Output');
$data = array();
foreach ($entityFilterLog as $filterStep) {
  $data[] = array(
    array(
      'valign' => 'top',
      'data' => t('[@replicaName] @step', array('@replicaName'=>$filterStep[0],'@step'=>$filterStep[1])),
    ),
    array(
      'valign' => 'top',
      'data' => $filterStep[2],
    ),
    array(
      'valign' => 'top',
      'data' => $filterStep[3],
    ),
  );
}
echo theme('table', $headers, $data);
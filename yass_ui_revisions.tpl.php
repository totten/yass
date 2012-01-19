<?php

require_once 'YASS/Engine.php';
$revTable = array();
$revTableHeader = array(t('Replica / Revision'), t('Entity'));

foreach ($revisions as $onReplicaName => $revisionInReplica) {
    $onReplica = YASS_Engine::singleton()->getReplicaByName($onReplicaName);
    foreach ($revisionInReplica as $revCode => $revision) {
      if ($revCode == 'head') {
        $revPath = sprintf('yass/entity/%s/%s', $onReplicaName, $revision->entityGuid);
        $revCodePretty = 'head(' . $revision->version->replicaId . ':' . $revision->version->tick . ')';
      } else {
        $revPath = sprintf('yass/entity/%s/%s/%s', $onReplicaName, $revision->entityGuid, $revCode);
        $revCodePretty = 'archive('.$revCode.')';
      }
      
      $revTable[] = array(
        array(
          'valign'=>'top', 
          'data' =>t('<div>@replicaName (@dataStore)<br/><a href="@revPath">@revCodePretty</a><br/>@timestamp', array(
            '@replicaName' => $onReplicaName,
            '@dataStore' => $onReplica->spec['datastore'],
            '@revPath' => url($revPath),
            '@revCodePretty' => $revCodePretty,
            '@timestamp' => isset($revision->timestamp) ? date('Y-m-d H:i:s', $revision->timestamp) : '',
          )),
        ),
        krumo_ob($revision),
      );
    }
}

echo theme('table', $revTableHeader, $revTable);

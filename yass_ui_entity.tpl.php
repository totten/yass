<?php
require_once 'YASS/Engine.php';
dpm($entity);
dpm($revisions);

$revTable = array();
$revTableHeader = array(t('Replica'), t('Revision'), t('Timestamp'), t('Extant'));

foreach ($revisions as $onReplicaName => $revisionInReplica) {
    $onReplica = YASS_Engine::singleton()->getReplicaByName($onReplicaName);
    foreach ($revisionInReplica as $revCode => $revision) {
      if ($revCode == 'head') {
        $revPath = sprintf('yass/entity/%s/%s', $onReplicaName, $revision->entityGuid);
        $revLink = l('head(' . $revision->version->replicaId . ':' . $revision->version->tick . ')', $revPath);
      } else {
        $revPath = sprintf('yass/entity/%s/%s/%s', $onReplicaName, $revision->entityGuid, $revCode);
        $revLink = l($revCode, $revPath);
      }
      $revTable[] = array(
        t('@replicaName (@dataStore)', array('@replicaName' => $onReplicaName, '@dataStore' => $onReplica->spec['datastore'])),
        $revLink,
        isset($revision->timestamp) ? date('Y-m-d H:i:s', $revision->timestamp) : '',
        $revision->exists ? t('Yes') : t('No'),
      );
    }
}

echo theme('table', $revTableHeader, $revTable);

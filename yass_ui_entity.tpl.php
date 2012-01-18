<?php
dpm($entity);

$revTable = array();
$revTableHeader = array(t('Revision'), t('Timestamp'), t('Extant'));
foreach ($revisions as $revision) {
  $revCode = $revision->version->replicaId . ':' . $revision->version->tick;
  $revTable[] = array(
    l($revCode, sprintf('yass/entity/%s/%s/%s', $replica->name, $entity->entityGuid, $revCode)),
    isset($revision->timestamp) ? date('Y-m-d H:i:s', $revision->timestamp) : '',
    $revision->exists ? t('Yes') : t('No'),
  );
}
echo theme('table', $revTableHeader, $revTable);

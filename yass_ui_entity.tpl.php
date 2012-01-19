<?
if ($revisions) {
  echo '<h2>Revisions</h2>';
  echo theme('yass_ui_revisions', $revisions);
}

if ($entityFilterLog) {
  echo (t('<h2>Filter Log (@replicaName @ @uReplicaId:@uTick)</h2>', array(
    '@replicaName' => $replica->name,
    '@entityType' => $entity->entityType ? $entity->entityType : 'Unknown',
    '@entityId' => $entity->entityGuid,
    '@uReplicaId' => $entity->version->replicaId,
    '@uTick' => $entity->version->tick,
  )));
  echo theme('yass_ui_filter_log', $entityFilterLog);
}

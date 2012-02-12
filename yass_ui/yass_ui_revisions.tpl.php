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
      
      $leftBlock = array();
      $leftBlock[] = check_plain($onReplicaName .' (' . $onReplica->spec['datastore'] . ')');
      $leftBlock[] = l($revCodePretty, $revPath);
      if (isset($revision->timestamp)) {
        $leftBlock[] = date('Y-m-d H:i:s', $revision->timestamp);
      }
      if (module_exists('arms_interlink') && $onReplica->spec['datastore'] == 'Proxy' && $revCode == 'head' && $revision->exists && $revision->entityType == 'civicrm_contact') {
        $onSite = arms_interlink_get($onReplica->spec['remoteSite']);
        list ($entityType, $lid) = $onReplica->mapper->toLocal($revision->entityGuid);
        $leftBlock[] = arms_interlink_l($onSite, t('view contact'), 'civicrm/contact/view', array(
          'query' => array(
            'reset' => 1,
            'cid' => $lid,
          ),
        ));
      }
      
      $revTable[] = array(
        array(
          'valign'=>'top',
          'data' => implode('<br/>', $leftBlock),
        ),
        krumo_ob($revision),
      );
    }
}

echo theme('table', $revTableHeader, $revTable);

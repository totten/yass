<?php

require_once 'YASS/Filter/SQLMap.php';

/**
 * Convert option values to different formats (e.g. convert an activity_type_id to a name)
 *
 * Note: This uses the *local* option group mappings; this may constrain which replicas can/should use it
 */
class YASS_Filter_OptionValue extends YASS_Filter_SQLMap {

  /**
   *
   * @param $spec array; keys: 
   *  - entityType: string, the type of entity to which the filter applies
   *  - field: string, the incoming field name
   *  - localFormat: string, the format used on $replicaId ('value', 'name', 'label')
   *  - globalFormat: string, the format used on normalized replicas ('value', 'name', 'label')
   *  - group: string, the name of the optiongroup containing values/names/labels (alt: groupId)
   *  - groupId: int, the id of the optiongroup containing values/names/labels (alt: group) 
   */
  function __construct($spec) {
    if ($spec['groupId']) {
      $spec['sql'] = sprintf('
        SELECT cov.%s local, cov.%s global
        FROM civicrm_option_value cov
        WHERE cov.option_group_id = %d
        ', $spec['localFormat'], $spec['globalFormat'],
        $spec['groupId']
      );
    } elseif ($spec['group']) {
      $spec['sql'] = sprintf('
        SELECT cov.%s local, cov.%s global
        FROM civicrm_option_value cov
        INNER JOIN civicrm_option_group cog on cov.option_group_id = cog.id
        WHERE cog.name = "%s"
        ', $spec['localFormat'], $spec['globalFormat'], 
        db_escape_string($spec['group'])
      );
    }
    parent::__construct($spec);
    $this->spec = $spec;
  }
}

<?php

/*
 +--------------------------------------------------------------------+
 | YASS                                                               |
 +--------------------------------------------------------------------+
 | Copyright ARMS Software LLC (c) 2011-2012                          |
 +--------------------------------------------------------------------+
 | This file is a part of YASS.                                       |
 |                                                                    |
 | YASS is free software; you can copy, modify, and distribute it     |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007.                                       |
 |                                                                    |
 | YASS is distributed in the hope that it will be useful, but        |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | Additional permissions may be granted. See LICENSE.txt for         |
 | details.                                                           |
 +--------------------------------------------------------------------+
*/

require_once 'YASS/Replica.php';

/**
 * This is a profile for replicas based on remote CiviCRM instances
 */
class YASS_Replica_ARMSProxy extends YASS_Replica {
    /**
     * Construct a replica based on saved configuration metadata
     *
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     *  - remoteSite: int or "#local"
     *  - remoteReplica: string, replica name
     *  - site: array
     */
    function __construct($replicaSpec) {
        $mandates = array(
            'datastore' => 'Proxy',
            'syncstore' => 'Proxy',
            'guid_mapper' => 'Proxy',
        );
        $replicaSpec = array_merge($replicaSpec, $mandates);
        if (empty($replicaSpec['remoteSite']) || empty($replicaSpec['remoteReplica'])) {
            throw new Exception('Missing remoteSite or remoteReplica field');
        }
        if (empty($replicaSpec['site'])) {
            throw new Exception('Missing site field');
        }
        parent::__construct($replicaSpec);
    }
    
    protected function createConflictListeners() {
        require_once 'YASS/ConflictListener/LogEntity.php';
        $result = parent::createConflictListeners();
        $result[] = new YASS_ConflictListener_LogEntity(array(
            'hackConflictLog' => '_yass_replica_armsproxy_hackConflictLog',
        ));
        return $result;
    }
    
    protected function createFilters() {
        $site = $this->spec['site'];
    
        $filters = parent::createFilters();
        // FIXME Get entity types from schema or configuration
        $syncableEntityTypes = array( // match YASS_Schema_CiviCRM::getEntityTypes / YASS_Schema_CiviCRM::$_ENTITIES
            'civicrm_contact', 'civicrm_address', 'civicrm_phone', 'civicrm_email', 'civicrm_website',
            'civicrm_activity', 'civicrm_activity_assignment', 'civicrm_activity_target',
            'yass_conflict', 'yass_mergelog',
        );
        
        $filters = array();
              
        require_once 'YASS/Filter/Rename.php';
        $filters[] = new YASS_Filter_Rename(array(
            'entityTypes' => $syncableEntityTypes,
            'local' => '#unknown/' . $this->spec['remoteReplica'],
            'global' => '#unknown/' . $this->name,
            'weight' => '0',
        ));
          
        switch ($site['gender']) {
            case 'Men':
            case 'Women':
                require_once 'YASS/Filter/Constants.php';
                $filters[] = new YASS_Filter_Constants(array(
                  'entityTypes' => $syncableEntityTypes,
                  'constants' => array(
                      '#custom/secSport' => $site['sport'],
                      '#custom/secGender' => $site['gender'],
                  ),
                  'weight' => 5,
                ));
                break;
                
            case 'Coed':
                require_once 'YASS/Filter/Constants.php';
                $filters[] = new YASS_Filter_Constants(array(
                  'entityTypes' => $syncableEntityTypes,
                  'constants' => array(
                      '#custom/secSport' => $site['sport'],
                  ),
                  'weight' => 5,
                ));
                break;
    
            case 'NA':
                require_once 'YASS/Filter/GenderSportRelations.php';
                $filters[] = new YASS_Filter_GenderSportRelations(array(
                  'secStore' => 'master',
                  'entityType' => 'civicrm_contact',
                  'relations' => array(
                      'civicrm_address' => 'contact_id',
                      'civicrm_phone' => 'contact_id',
                      'civicrm_email' => 'contact_id',
                      'civicrm_website' => 'contact_id',
                      // 'civicrm_activity_assignment' => 'assignee_contact_id', // tricky corner cases involving civicrm_activity and varying security boundaries
                      // 'civicrm_activity_target' => 'target_contact_id', // tricky corner cases involving civicrm_activity and varying security boundaries
                  ),
                  'fallback' => array(
                      'secGender' => $site['gender'],
                      'secSport' => $site['sport'],
                  ),
                  'weight' => 5,
                ));
                break;
    
            default:
                throw new Exception(sprintf('Unsupported site: site_id=[%s] site_url=[%s] gender=[%s] sport=[%s]', $site['site_id'], $site['site_url'], $site['gender'], $site['sport']));
        }
        
        require_once 'YASS/Filter/StdColumns.php';    
        $filters[] = new YASS_Filter_StdColumns(array(
          'weight' => 10,
        ));
        
        return $filters;
    }
}

function _yass_replica_armsproxy_hackConflictLog($data, YASS_Conflict $conflict) {
    $data['#custom']['secGender'] = $conflict->winner->entity->data['#custom']['secGender'];
    $data['#custom']['secSport'] = $conflict->winner->entity->data['#custom']['secSport'];
    return $data;
}

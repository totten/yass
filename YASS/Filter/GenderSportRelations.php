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

require_once 'YASS/Filter.php';

/**
 * Copy secGender/secSport from a parent entity (civicrm_contact) to subordinate, related entities (civicrm_email,civicrm_address,...).
 *
 * If the parent entity is in the current batch, read the values from the current batch. Otherwise, read the values from an
 * auxiliary data-store.
 *
 * TODO: remove hard reference to secGender/secSport; rename to something like 'YASS_Filter_TransitiveProperty'
 */
class YASS_Filter_GenderSportRelations extends YASS_Filter {

    /**
     *
     * @param $spec array; keys: 
     *  - secStore: string, the name of the datastore from which we can lookup missing secGender/secSport
     *  - entityType: string, the primary entity which stores the (sport,gender) tuple
     *  - relations: array($entityType=>$columnName), related entities for which (sport,gender) must be determined indirectly
     *  - fallback: array(secGender=>$,secSport=>$), fallback values to use if unavailable
     */
    function __construct($spec) {
        parent::__construct($spec);
        arms_util_include_api('array');
        require_once 'YASS/Engine.php';
        require_once 'YASS/Log.php';
        $this->_log = YASS_Log::instance('YASS_Filter_GenderSportRelations');
    }
    
    /**
     * Don't send secGender,secSport to local datastore
     */
    function toLocal(&$entities, YASS_Replica $replica) {
        foreach ($entities as $entity) {
            // FIXME: Replica should have native support for storing these fields on contacts
            // if ($this->spec['relations'][$entity->entityType]) {
            if ($entity->entityType != $this->spec['entityType'] && is_array($entity->data['#custom'])) {
                foreach (array('secGender', 'secSport') as $key) {
                    if (array_key_exists($key, $entity->data['#custom'])) {
                        unset($entity->data['#custom'][$key]);
                    }
                }
            }
        }
    }
    
    /**
     * Update civicrm_{email,address,...} to use the secGender/Sport of the related contact
     */
    function toGlobal(&$entities, YASS_Replica $replica) {
        $secStore = YASS_Engine::singleton()->getReplicaByName($this->spec['secStore']);
        if (!is_object($secStore)) {
            throw new Exception(sprintf('Failed to locate secStore [%s]', $this->spec['secStore']));
        }
        
        $secValues = array(); // array($contactEntityGuid => array(secGender => $, secSport => $))
        $contactGuids = array(); // array($contactEntityGuid)
        
        // Pass #1: Copy secGender/secSport of any contacts to $secValues. Identify contacts which need to be looked up.
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if ($this->spec['entityType'] == $entity->entityType) {
                if (! ($entity->data['#custom']['secGender'] && $entity->data['#custom']['secSport'])) {
                    $this->_log->infof('toGlobal(%s): fill in sport(%s) and gender(%s)', $entity->entityGuid, $this->spec['fallback']['secSport'], $this->spec['fallback']['secGender']);
                    // ex: old record which never had its secGender/secSport set
                    $entity->data['#custom']['secGender'] = $this->spec['fallback']['secGender'];
                    $entity->data['#custom']['secSport'] = $this->spec['fallback']['secSport'];
                }
                $secValues[$entity->entityGuid] = array(
                    'secGender' => $entity->data['#custom']['secGender'],
                    'secSport' => $entity->data['#custom']['secSport'],
                );
                continue;
                // */
            }
            if ($relationColumn = $this->spec['relations'][$entity->entityType]) {
                if ($entity->data[$relationColumn]) {
                    $contactGuids[] = $entity->data[$relationColumn];
                } // else: e.g. civicrm_{email,address}.contact_id is blank for domain records
            }
        }
        
        // Pass #2: Lookup secGender/secSport of any contacts that weren't included in $entities
        $missingGuids = array_diff($contactGuids, array_keys($secValues));
        $missingEntities = $secStore->data->getEntities($missingGuids);
        foreach ($missingEntities as $missingEntity) {
            if (!$missingEntity->exists) {
                // Since Civi maintains ref-integ, and since $missingEntity was referenced by
                // a related Civi record in pass #1, we conclude that $missingEntity does+should
                // exist on replica but not yet exist on secStore.
                $secValues[$missingEntity->entityGuid] = $this->spec['fallback'];
            } elseif ($missingEntity->data['#custom']['secGender'] && $missingEntity->data['#custom']['secSport']) {
                $secValues[$missingEntity->entityGuid] = array(
                    'secGender' => $missingEntity->data['#custom']['secGender'],
                    'secSport' => $missingEntity->data['#custom']['secSport'],
                );
            }
        }
        
        // Pass #3: Fill in secGender/secSport for any related entities
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if ($relationColumn = $this->spec['relations'][$entity->entityType]) {
                if ($secValues[ $entity->data[$relationColumn] ]) {
                    $secValue = $secValues[ $entity->data[$relationColumn] ];
                } else {
                    // e.g. civicrm_{email,address}.contact_id is blank for domain records
                    $secValue = $this->spec['fallback'];
                }
                if (empty($secValue) || empty($secValue['secGender']) || empty($secValue['secSport'])) {
                    throw new Exception(sprintf('Failed to determine secValue for replica=[%s] entityType=[%s] entityGuid=[%s] relationCol=[%s] relationGuid=[%s]',
                        $replica->name,
                        $entity->entityType,
                        $entity->entityGuid,
                        $relationColumn,
                        $entity->data[$relationColumn]
                    ));
                } 
                $entity->data['#custom']['secGender'] = $secValue['secGender'];
                $entity->data['#custom']['secSport'] = $secValue['secSport'];
            }
        }
    }
}

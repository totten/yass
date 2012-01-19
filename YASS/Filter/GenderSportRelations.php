<?php

require_once 'YASS/Filter.php';
require_once 'YASS/Version.php';

/**
 * Copy secGender/secSport from civicrm_contact to corresponding civicrm_{email,address,...}
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
	}
	
	/**
	 * Don't send secGender,secSport to site
	 */
	function toLocal(&$entities, YASS_Replica $replica) {
		foreach ($entities as $entity) {
			// FIXME: Replica should have native support for storing these fields on contacts
			// if ($this->spec['relations'][$entity->entityType]) {
			if ($entity->entityType != 'civicrm_contact') {
				unset($entity->data['#custom']['secGender']);
				unset($entity->data['#custom']['secSport']);
			}
		}
	}
	
	/**
	 * Update civicrm_{email,address,...}.contact_id to use the secGender/Sport of the related contact
	 */
	function toGlobal(&$entities, YASS_Replica $replica) {
		$secStore = YASS_Engine::singleton()->getReplicaByName($this->spec['secStore']);
		if (!is_object($secStore)) {
			throw new Exception(sprintf('Failed to locate secStore [%s]', $this->spec['secStore']));
		}
		
		$secValues = array(); // array($contactEntityGuid => array(secGender => $, secSport => $))
		$contactGuids = array(); // array($contactEntityGuid)
		
		// Pass #1: Copy secGender/secSport of any contacts to $secValues. Identify contacts which need need to be looked up.
		foreach ($entities as $entity) {
			if (!$entity->exists) continue;
			if ($this->spec['entityType'] == $entity->entityType) {
				if ($entity->data['#custom']['secGender'] && $entity->data['#custom']['secSport']) {
					$secValues[$entity->entityGuid] = array(
						'secGender' => $entity->data['#custom']['secGender'],
						'secSport' => $entity->data['#custom']['secSport'],
					);
					continue;
				}
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
				if (empty($secValue)) {
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

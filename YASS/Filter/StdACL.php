<?php

require_once 'YASS/Filter.php';

/**
 * A filter for the master replica which uses interlink descriptors to generate
 * #acl lists for incoming entities.
 *
 * This is hard-coded to the (gender,sport) security model. For more creative
 * arrangements (eg based on contact-status), we need to reimplement.
 */
class YASS_Filter_StdACL extends YASS_Filter {
	/**
	 * Look up table; given the (gender,sport) for a record, determine which replicas have access to that record.
	 *
	 * Note that gender/sport here explicitly excludes pseudo
	 *
	 * @var array(gender => array(sport => array(replicaId)))
	 */
	var $limitedReplicaIds;
	
	/**
	 * @var array(replicaId)
	 */
	var $superReplicaIds;

	/**
	 *
	 * Note: arms_util_array_combine_properties(YASS_Engine::singleton()->getReplicas(), 'name', 'id')
	 *
	 * @param $spec array; keys: 
	 *  - entityTypes: array(entityType)
	 *  - sites: interlink descriptors;
	 *    array(array('site_id' => int, 'site_url' => domain.name, 'gender' => Men|Women|Coed|NA, 'sport' => sportName))
	 *  - replicaIdsByName: array(replicaName => replicaId)
	 */
	function __construct($spec) {
		if (!isset($spec['delim'])) {
			$spec['delim'] = '/';
		}
		parent::__construct($spec);
		arms_util_include_api('array');
		$this->entityTypes = drupal_map_assoc($spec['entityTypes']);
		list($this->limitedReplicaIds, $this->superReplicaIds) = $this->createAclIndexes($spec['sites'], $spec['replicaIdsByName']);
	}
	
	/**
	 * Create a lookup table -- given the (gender,sport) for a record, determine which replicas
	 * have access to that record.
	 *
	 * Note that pseudo-genders/pseudo-sports may be used in the input (site descriptors) but
	 * not in the output (lookup-table).
	 *
	 * @param $sites interlink descriptors;
	 *    array(array('site_id' => int, 'site_url' => domain.name, 'gender' => Men|Women|Coed|NA, 'sport' => sportName))
	 * @param $replicaIdsByName array(replicaName => replicaId)
	 * @return array of:
	 *   0: array(gender => array(sport => array(replicaId)))
	 *   1: array(replicaId)
	 *   
	 */
	function createAclIndexes($sites, $replicaIdsByName) {
		$limitedReplicas = array(); // array(gender => array(sport => array(replicaId)))
		$superReplicaIds = array(); // array(replicaId), list of replicas which access all entities
		
		foreach ($sites as $site) {
			$replicaId = $replicaIdsByName[$site['site_url']];
			if (!$replicaId) continue;
			
			switch($site['gender']) {
				case 'Men':
					$limitedReplicas['Men'  ][ $site['sport'] ][] = $replicaId;
					break;
				case 'Women':
					$limitedReplicas['Women'][ $site['sport'] ][] = $replicaId;
					break;
				case 'Coed':
					$limitedReplicas['Men'  ][ $site['sport'] ][] = $replicaId;
					$limitedReplicas['Women'][ $site['sport'] ][] = $replicaId;
					break;
				case 'NA':
					$superReplicaIds[] = $replicaId;
					break;
				default:
					throw new Exception(sprintf('Unsupported site: site_id=[%s] site_url=[%s] gender=[%s] sport=[%s]', $site['site_id'], $site['site_url'], $site['gender'], $site['sport']));
			}
		}
		
		return array($limitedReplicas, $superReplicaIds);
	}
	
	function toLocal(&$entities, YASS_Replica $replica) {
		foreach ($entities as $entity) {
			if (isset($this->entityTypes[$entity->entityType])) {
				$entity->data['#acl'] = $this->createAcl($entity->data['#custom']['secGender'], $entity->data['#custom']['secSport']);
			}
			// FIXME: Define default ACL for oddball entities using the creator/source replica ID
		}
	}
	
	function toGlobal(&$entities, YASS_Replica $replica) {
		foreach ($entities as $entity) {
			if (isset($this->entityTypes[$entity->entityType])) {
				unset($entity->data['#acl']);
			}
		}
	}
	
	/**
	 * Determine the ACL for an entity given its gender and sport
	 *
	 * @return array(replicaId)
	 */
	function createAcl($gender, $sport) {
		if ($this->limitedReplicaIds[$gender][$sport]) {
			return array_merge($this->limitedReplicaIds[$gender][$sport], $this->superReplicaIds);
		} else {
			return $this->superReplicaIds;
		}
	}
}
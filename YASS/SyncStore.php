<?php

require_once 'YASS/ReplicaListener.php';
require_once 'YASS/Version.php';

abstract class YASS_SyncStore extends YASS_ReplicaListener {
	/**
	 * Find a list of revisions that have been previously applied to a replica
	 *
	 * @return array of YASS_Version keyed by replicaId
	 */
	abstract function getLastSeenVersions();
	
	/**
	 * Assert that the given replica includes the data for (replicaId,tick)
	 */
	abstract function markSeen(YASS_Version $lastSeen);
	
	/**
	 * Find all records in a replica which have been modified since the given point
	 *
	 * @return array(entityGuid => YASS_SyncState)
	 */
	abstract function getModified(YASS_Version $lastSeen = NULL);
	
	/**
	 * Find all records in a replica which have been modified since the given point
	 *
	 * @param $remoteLastSeenVersions array(replicaId => YASS_Version) List version records which have already been seen
	 * @return array(entityGuid => YASS_SyncState)
	 */
	function getModifieds($remoteLastSeenVersions) {
		$localChanges = array();  // array(entityGuid => YASS_SyncState)
		$localLastSeenVersions = $this->getLastSeenVersions();
		foreach ($localLastSeenVersions as $replicaId => $localVersion) {
			$remoteVersion = $remoteLastSeenVersions[$replicaId] ? $remoteLastSeenVersions[$replicaId] : new YASS_Version($replicaId, 0);
			// print_r(array('localChanges += ', $this->getModified($remoteVersion)));
			$localChanges += $this->getModified($remoteVersion);
		}
		return $localChanges;
	}
	
	/**
	 * Determine the sync state of a particular entity
	 *
	 * @return YASS_SyncState
	 */
	abstract function getSyncState($entityGuid);
	
	/**
	 * Set the sync state of an entity
	 */
	abstract function setSyncState($entityGuid, YASS_Version $modified);
	
	/**
	 * Set the sync states of several entities
	 *
	 * @param $states array(entityGuid => YASS_Version)
	 */
	function setSyncStates($states) {
		foreach ($states as $entityGuid => $modified) {
			$this->setSyncState($entityGuid, $modified);
		}
	}
	
	/**
	 * Forcibly increment the versions of entities to make the current replica appear newest
	 */
	abstract function updateAllVersions();
	
	/**
	 * Destroy any last-seen or sync-state data
	 */
	abstract function destroy();
}

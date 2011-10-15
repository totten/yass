<?php

require_once 'YASS/Version.php';

abstract class YASS_SyncStore {
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
	 * Determine the sync state of a particular entity
	 *
	 * @return YASS_SyncState
	 */
	abstract function getSyncState($entityGuid);
	
	/**
	 * Set the sync state of an entity
	 */
	abstract function setSyncState($entityGuid, YASS_Version $modified);

}

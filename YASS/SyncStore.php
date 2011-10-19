<?php

require_once 'YASS/Version.php';
require_once 'YASS/IReplicaListener.php';

abstract class YASS_SyncStore implements YASS_IReplicaListener {
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
	
	/**
	 * Destroy any last-seen or sync-state data
	 */
	abstract function destroy();

	function onChangeId(YASS_Replica $replica, $oldId, $newId) {}
	function onPostJoin(YASS_Replica $replica, YASS_Replica $master) {}
	function onPostRejoin(YASS_Replica $replica, YASS_Replica $master) {}
	function onPostReset(YASS_Replica $replica, YASS_Replica $master) {}
	function onPostSync(YASS_Replica $replica) {}
	function onPreJoin(YASS_Replica $replica, YASS_Replica $master) {}
	function onPreRejoin(YASS_Replica $replica, YASS_Replica $master) {}
	function onPreReset(YASS_Replica $replica, YASS_Replica $master) {}
	function onPreSync(YASS_Replica $replica){}
}

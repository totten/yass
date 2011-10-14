<?php

require_once 'YASS/Algorithm.php';
require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/ConflictResolver.php';

class YASS_Algorithm_Bidir extends YASS_Algorithm {
	function run(
		YASS_DataStore $srcData, YASS_SyncStore $srcSync,
		YASS_DataStore $destData, YASS_SyncStore $destSync,
		YASS_ConflictResolver $conflictResolver
	) {
		arms_util_include_api('array');
		
		$this->srcData = $srcData;
		$this->srcSync = $srcSync;
		$this->destData = $destData;
		$this->destSync = $destSync;
		$this->conflictResolver = $conflictResolver;

		// BEGIN transaction
		
		$srcLastSeenVersions = $srcSync->getLastSeenVersions();    // array(replicaId => YASS_Version)
		$destLastSeenVersions = $destSync->getLastSeenVersions(); // array(replicaId => YASS_Version)
		$srcChanges = array();  // array(entityGuid => YASS_SyncState)
		$destChanges = array(); // array(entityGuid => YASS_SyncState)
		foreach ($srcLastSeenVersions as $replicaId => $srcVersion) {
			$destVersion = $destLastSeenVersions[$replicaId] ? $destLastSeenVersions[$replicaId] : NULL;
			// print_r(array('srcChanges += ', $srcSync->getModified($destVersion)));
			$srcChanges += $srcSync->getModified($destVersion);
		}
		foreach ($destLastSeenVersions as $replicaId => $destVersion) {
			$srcVersion = $srcLastSeenVersions[$replicaId] ? $srcLastSeenVersions[$replicaId] : NULL;
			// print_r(array('destChanges +=', $destSync->getModified($srcVersion)));
			$destChanges += $destSync->getModified($srcVersion);
		}

		// A conflict arises when srcChanges and destChanges reference the same entityGuid
		$srcChangesClean = array_diff(array_keys($srcChanges), array_keys($destChanges));
		$destChangesClean = array_diff(array_keys($destChanges), array_keys($srcChanges));
		$conflictedChanges = array_intersect(array_keys($srcChanges), array_keys($destChanges));
		
		// print_r(array('srcLastSeenVersions' => $srcLastSeenVersions, 'destLastSeenVersions' => $destLastSeenVersions, 'srcChanges' => $srcChanges, 'destChanges' => $destChanges,'srcChangesClean' => $srcChangesClean,'destChangesClean' => $destChangesClean, 'conflictedChanges' => $conflictedChanges,));
		
		$this->transfer($srcData, $srcSync, $destData, $destSync, arms_util_array_keyslice($srcChanges, $srcChangesClean));
		$this->transfer($destData, $destSync, $srcData, $srcSync, arms_util_array_keyslice($destChanges, $destChangesClean));
		
		foreach ($conflictedChanges as $entityGuid) {
			$conflictResolver->resolve($this, $srcChanges[$entityGuid], $destChanges[$entityGuid]);
		}
		
		foreach ($destLastSeenVersions as $destVersion) {
			$srcSync->markSeen($destVersion);
		}
		
		foreach ($srcLastSeenVersions as $srcVersion) {
			$destSync->markSeen($srcVersion);
		}
		
		// print_r(array('srcSync' => $srcSync, 'destSync' => $destSync, 'srcData' => $srcData, 'destData' => $destData));

		// COMMIT transaction
	}
	
	/**
	 * Transfer a set of records from one replica to another
	 *
	 * @param $syncStates array(YASS_SyncState) List of entities/revisions to transfer
	 */
	function transfer(
		YASS_DataStore $srcData, YASS_SyncStore $srcSync,
		YASS_DataStore $destData, YASS_SyncStore $destSync,
		$syncStates)
	{
		foreach ($syncStates as $srcSyncState) {
			$entity = $srcData->getEntity($srcSyncState->entityType, $srcSyncState->entityGuid);
			$destData->putEntity($entity);
			$destSync->setSyncState($srcSyncState->entityType, $srcSyncState->entityGuid, $srcSyncState->modified);
		}
	}
}

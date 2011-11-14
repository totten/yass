<?php

require_once 'YASS/Algorithm.php';
require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/ConflictResolver.php';

class YASS_Algorithm_Bidir extends YASS_Algorithm {
	function run(
		YASS_Replica $src,
		YASS_Replica $dest,
		YASS_ConflictResolver $conflictResolver
	) {
		arms_util_include_api('array');
		
		$this->src = $src;
		$this->dest = $dest;
		$this->conflictResolver = $conflictResolver;

		// BEGIN transaction
		
		$srcLastSeenVersions = $src->sync->getLastSeenVersions();    // array(replicaId => YASS_Version)
		$destLastSeenVersions = $dest->sync->getLastSeenVersions(); // array(replicaId => YASS_Version)
		$srcChanges = $src->sync->getModifieds($destLastSeenVersions); // array(entityGuid => YASS_SyncState)
		$destChanges = $dest->sync->getModifieds($srcLastSeenVersions); // array(entityGuid => YASS_SyncState)

		// A conflict arises when srcChanges and destChanges reference the same entityGuid
		$srcChangesClean = array_diff(array_keys($srcChanges), array_keys($destChanges));
		$destChangesClean = array_diff(array_keys($destChanges), array_keys($srcChanges));
		$conflictedChanges = array_intersect(array_keys($srcChanges), array_keys($destChanges));
		
		// print_r(array('srcLastSeenVersions' => $srcLastSeenVersions, 'destLastSeenVersions' => $destLastSeenVersions, 'srcChanges' => $srcChanges, 'destChanges' => $destChanges,'srcChangesClean' => $srcChangesClean,'destChangesClean' => $destChangesClean, 'conflictedChanges' => $conflictedChanges,));
		
		YASS_Engine::singleton()->transfer($src, $dest, arms_util_array_keyslice($srcChanges, $srcChangesClean));
		YASS_Engine::singleton()->transfer($dest, $src, arms_util_array_keyslice($destChanges, $destChangesClean));
		
		foreach ($conflictedChanges as $entityGuid) {
			$conflictResolver->resolve($this, $srcChanges[$entityGuid], $destChanges[$entityGuid]);
		}
		
		foreach ($destLastSeenVersions as $destVersion) {
			$src->sync->markSeen($destVersion);
		}
		
		foreach ($srcLastSeenVersions as $srcVersion) {
			$dest->sync->markSeen($srcVersion);
		}
		
		// print_r(array('srcSync' => $src->sync, 'destSync' => $dest->sync, 'srcData' => $src->data, 'destData' => $dest->data));

		// COMMIT transaction
	}
	
}

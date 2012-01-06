<?php

require_once 'YASS/Engine.php';
require_once 'YASS/ConflictResolver.php';

/**
 * A conflict resolver for use with background synchronizations in which 
 * we need to make a best-effort guess.
 *
 * Note: Src should be an ARMS site, and dest should be the master.
 *
 * In general, we use SrcWins (active-ARMS-site-wins). But in the case
 * where an item disappeared on the master, we allow the master to win.
 * This is sensible when the disappearance stems from a change in
 * access-control.
 */
class YASS_ConflictResolver_Auto extends YASS_ConflictResolver {
	function resolve(YASS_Algorithm $job, YASS_SyncState $srcSyncState, YASS_SyncState $destSyncState) {
		$guid = $destSyncState->entityGuid;
		$destEntities = $job->dest->data->getEntities(array($guid));
		if ($destEntities[$guid]->exists) {
			YASS_Engine::singleton()->transfer($job->src, $job->dest, array($srcSyncState));
		} else {
			YASS_Engine::singleton()->transfer($job->dest, $job->src, array($srcSyncState));
		}
	}
}
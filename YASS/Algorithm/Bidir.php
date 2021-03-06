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

require_once 'YASS/Algorithm.php';
require_once 'YASS/Conflict.php';
require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/ConflictResolver.php';
require_once 'YASS/Pairing.php';

class YASS_Algorithm_Bidir extends YASS_Algorithm {

    function __construct() {
        require_once 'YASS/Log.php';
        $this->_log = YASS_Log::instance('YASS_Algorithm_Bidir');
    }
    
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
        
        $this->_log->info($src->getDesc() . ' ==> ' . $dest->getDesc());
        $this->_log->debug(array('srcLastSeenVersions' => $srcLastSeenVersions, 'destLastSeenVersions' => $destLastSeenVersions, 'srcChanges' => $srcChanges, 'destChanges' => $destChanges,'srcChangesClean' => $srcChangesClean,'destChangesClean' => $destChangesClean, 'conflictedChanges' => $conflictedChanges));
        
        $conflicts = YASS_Conflict::createBatch($src, $dest, $conflictedChanges, $srcChanges, $destChanges);
        $conflictResolver->resolveAll($conflicts);
        foreach ($conflicts as $conflict) {
            if ($conflict->winner->replica->id == $src->id) {
                $srcChangesClean[] = $conflict->winner->syncState->entityGuid;
            } elseif ($conflict->winner->replica->id == $dest->id) {
                $destChangesClean[] = $conflict->winner->syncState->entityGuid;
            }
        }
        
        YASS_Engine::singleton()->transfer($src, $dest, arms_util_array_keyslice($srcChanges, $srcChangesClean));
        YASS_Engine::singleton()->transfer($dest, $src, arms_util_array_keyslice($destChanges, $destChangesClean));
        
        $src->sync->markSeens($destLastSeenVersions);
        $dest->sync->markSeens($srcLastSeenVersions);
        
        // print_r(array('srcSync' => $src->sync, 'destSync' => $dest->sync, 'srcData' => $src->data, 'destData' => $dest->data));

        // COMMIT transaction
    }
    
}

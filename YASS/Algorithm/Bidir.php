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

require_once 'YASS/Addendum.php';
require_once 'YASS/Algorithm.php';
require_once 'YASS/Conflict.php';
require_once 'YASS/Context.php';
require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/ConflictResolver.php';
require_once 'YASS/Pairing.php';

class YASS_Algorithm_Bidir extends YASS_Algorithm {
    function run(
        YASS_Replica $src,
        YASS_Replica $dest,
        YASS_Replica $addendum,
        YASS_ConflictResolver $conflictResolver
    ) {
        arms_util_include_api('array');
        $ctx = new YASS_Context(array(
            'action' => 'bidir',
            'addendum' => new YASS_Addendum($addendum),
            'pairing' => new YASS_Pairing($src, $dest)
        ));
        
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
        
        $conflicts = YASS_Conflict::createBatch($src, $dest, $conflictedChanges, $srcChanges, $destChanges);
        $conflictResolver->resolveAll($conflicts);
        
        $src->sync->markSeens($destLastSeenVersions);
        $dest->sync->markSeens($srcLastSeenVersions);
        
        YASS_Context::get('addendum')->apply(array($src, $dest));
        
        // print_r(array('srcSync' => $src->sync, 'destSync' => $dest->sync, 'srcData' => $src->data, 'destData' => $dest->data));

        // COMMIT transaction
    }
    
}

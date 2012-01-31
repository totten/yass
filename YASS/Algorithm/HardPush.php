<?php

require_once 'YASS/Algorithm.php';
require_once 'YASS/Context.php';
require_once 'YASS/DataStore.php';
require_once 'YASS/SyncStore.php';
require_once 'YASS/ConflictResolver.php';
require_once 'YASS/Pairing.php';

/**
 * Forcibly transfer all entities and syncstates from $src to $dest, regardless of the
 * previous statue of $dest.
 */
class YASS_Algorithm_HardPush extends YASS_Algorithm {
    function run(
        YASS_Replica $src,
        YASS_Replica $dest,
        YASS_ConflictResolver $conflictResolver
    ) {
        arms_util_include_api('array');
        $ctx = new YASS_Context(array(
            'action' => 'hardpush',
            'pairing' => new YASS_Pairing($src, $dest)
        ));

        // BEGIN transaction
        $srcLastSeenVersions = $src->sync->getLastSeenVersions(); // array(replicaId => YASS_Version)
        $srcChanges = $src->sync->getModifieds(array()); // array(entityGuid => YASS_SyncState)

        // print_r(array('srcLastSeenVersions' => $srcLastSeenVersions, 'srcChanges' => $srcChanges));
        
        YASS_Engine::singleton()->transfer($src, $dest, $srcChanges);
        
        $dest->sync->markSeens($srcLastSeenVersions);

        // COMMIT transaction
    }
    
}

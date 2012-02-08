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

require_once 'YASS/ConflictResolver.php';

class YASS_ConflictResolver_Exception extends YASS_ConflictResolver {
    protected function resolve(YASS_Conflict $conflict) {
        // Note: The replicas being synchronized may not necessarily be the replicas which produced the changes
        $leftModifiedReplica = YASS_Engine::singleton()->getReplicaById($conflict->left->syncState->modified->replicaId);
        $leftModifiedName = ($leftModifiedReplica ? $leftModifiedReplica->name : $conflict->left->syncState->modified->replicaId);
        $rightModifiedReplica = YASS_Engine::singleton()->getReplicaById($conflict->right->syncState->modified->replicaId);
        $rightModifiedName = ($rightModifiedReplica ? $rightModifiedReplica->name : $conflict->right->syncState->modified->replicaId);
        throw new Exception(sprintf('Conflict detected for %s: (%s:%s via %s) vs (%s:%s via %s))',
            $conflict->entityGuid,
            $leftModifiedName, $conflict->left->syncState->modified->tick, $conflict->left->replica->name,
            $rightModifiedName, $conflict->right->syncState->modified->tick, $conflict->right->replica->name
        ));
    }
}

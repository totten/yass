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

require_once 'YASS/Replica.php';

/**
 * This is a profile for fake replicas which simply dump entity data to the console
 */
class YASS_Replica_Console extends YASS_Replica {

    /**
     * Construct a replica based on saved configuration metadata
     *
     * @param $replicaSpec array{yass_replicas} Specification for the replica
     */
    function __construct($replicaSpec) {
        $mandates = array(
            'datastore' => 'Console',
            'syncstore' => 'Console',
        );
        $replicaSpec = array_merge($replicaSpec, $mandates);
        parent::__construct($replicaSpec);
    }
}

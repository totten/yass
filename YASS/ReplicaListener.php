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

require_once 'YASS/IReplicaListener.php';

class YASS_ReplicaListener implements YASS_IReplicaListener {
    function onBuildFilters(YASS_Replica $replica) { return array(); }
    function onChangeId(YASS_Replica $replica, $oldId, $newId) {}
    function onCreateSqlProcedures(YASS_Replica $replica) { return array(); }
    function onCreateSqlTriggers(YASS_Replica $replica) { return array(); }
    function onPostJoin(YASS_Replica $replica, YASS_Replica $master) {}
    function onPostHardPush(YASS_Replica $src, YASS_Replica $dest) {}
    function onPostSync(YASS_Replica $replica) {}
    function onPreJoin(YASS_Replica $replica, YASS_Replica $master) {}
    function onPreHardPush(YASS_Replica $src, YASS_Replica $dest) {}
    function onPreSync(YASS_Replica $replica){}
    function onValidateGuids(YASS_Replica $replica) {}
}

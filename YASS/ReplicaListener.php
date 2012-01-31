<?php

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

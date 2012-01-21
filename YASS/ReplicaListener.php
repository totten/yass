<?php

require_once 'YASS/IReplicaListener.php';

class YASS_ReplicaListener implements YASS_IReplicaListener {
    function onBuildFilters(YASS_Replica $replica) { return array(); }
    function onChangeId(YASS_Replica $replica, $oldId, $newId) {}
    function onCreateSqlProcedures(YASS_Replica $replica) { return array(); }
    function onCreateSqlTriggers(YASS_Replica $replica) { return array(); }
    function onPostJoin(YASS_Replica $replica, YASS_Replica $master) {}
    function onPostRejoin(YASS_Replica $replica, YASS_Replica $master) {}
    function onPostReset(YASS_Replica $replica, YASS_Replica $master) {}
    function onPostSync(YASS_Replica $replica) {}
    function onPreJoin(YASS_Replica $replica, YASS_Replica $master) {}
    function onPreRejoin(YASS_Replica $replica, YASS_Replica $master) {}
    function onPreReset(YASS_Replica $replica, YASS_Replica $master) {}
    function onPreSync(YASS_Replica $replica){}
    function onValidateGuids(YASS_Replica $replica) {}
}

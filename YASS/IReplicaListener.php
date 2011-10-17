<?php

/**
 * Object-oriented variation of hook_yass_replica
 *
 * This is just a documentation stub which demonstrates that YASS_Replica,
 * YASS_DataStore, and YASS_SyncStore can listen to hook_yass_replica.
 */
interface YASS_IReplicaListener {
  function onPostJoin(YASS_Replica $replica, YASS_Replica $master);
  function onPostRejoin(YASS_Replica $replica, YASS_Replica $master);
  function onPostReset(YASS_Replica $replica, YASS_Replica $master);
  function onPostSync(YASS_Replica $replica);
  function onPreJoin(YASS_Replica $replica, YASS_Replica $master);
  function onPreRejoin(YASS_Replica $replica, YASS_Replica $master);
  function onPreReset(YASS_Replica $replica, YASS_Replica $master);
  function onPreSync(YASS_Replica $replica);
}

<?php

/**
 * Object-oriented variation of hook_yass_replica
 *
 * This is just a documentation stub which demonstrates that YASS_Replica,
 * YASS_DataStore, and YASS_SyncStore can listen to hook_yass_replica.
 */
interface YASS_IReplicaListener {
  /**
   * Update data-storage to reflect a change in ID
   *
   * This should not generally be used to trigger business-logic -- it's intended
   * only to maintain consistency of the backing store. For example, with
   * SyncStore_GenericSQL, one must update "yass_syncstore_state.replica_id" to ensure
   * that syncstates are still part of syncstore. However, the "u_replica_id"
   * or "c_replica_id" would be not be affected by an onChangeId event.
   */
  function onChangeId(YASS_Replica $replica, $oldId, $newId);
  
  function onPostJoin(YASS_Replica $replica, YASS_Replica $master);
  function onPostRejoin(YASS_Replica $replica, YASS_Replica $master);
  function onPostReset(YASS_Replica $replica, YASS_Replica $master);
  function onPostSync(YASS_Replica $replica);
  function onPreJoin(YASS_Replica $replica, YASS_Replica $master);
  function onPreRejoin(YASS_Replica $replica, YASS_Replica $master);
  function onPreReset(YASS_Replica $replica, YASS_Replica $master);
  function onPreSync(YASS_Replica $replica);
  
  /**
   * Delegate for hook_arms_triggers
   */
  function onCreateSqlTriggers(YASS_Replica $replica);
  
  /**
   * Ensure that any local-global mappings have been prepared. Generally
   * called when joining a replica, enabling a module, etc.
   *
   * Likely use: create GUIDs for any local entities that don't have them
   */
  function onValidateGuids(YASS_Replica $replica);
  
  /**
   * Build a list of fliters to apply to a transfer
   *
   * @return array(YASS_Filter)
   */
  function onBuildFilters(YASS_Replica $replica);
}

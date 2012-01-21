<?php

require_once 'YASS/Replica.php';

/**
 * A pair of replicas which are interacting
 */
class YASS_Pairing {
    /**
     * @var array(0 => YASS_Replica, 1 => YASS_Replica)
     */
    var $partners;
    
    /**
     *
     */
    function __construct(YASS_Replica $src, YASS_Replica $dest) {
        $this->partners = array($src, $dest);
    }
        
    /**
     * Determine the other partner involved in this transaction
     *
     * @param $myReplicaId int, the replica which needs to know its partner
     * @return YASS_Replica
     */
    function getPartner($myReplicaId) {
        if ($myReplicaId instanceof YASS_Replica) {
            $myReplicaId = $myReplicaId->id;
        }
        if (!is_numeric($myReplicaId)) {
            throw new RuntimeException('Failed to determine partner for non-numeric replica ID');
        }
        foreach ($this->partners as $replica) {
            if ($replica->id != $myReplicaId) {
                return $replica;
            }
        }
        throw new RuntimeException(sprintf('Failed to determine partner for myReplicaId=%d', $myReplicaId));
    }
}

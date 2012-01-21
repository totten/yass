<?php

require_once 'YASS/ReplicaListener.php';

abstract class YASS_Schema extends YASS_ReplicaListener {
    abstract function getEntityTypes();
}

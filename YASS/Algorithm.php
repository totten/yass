<?php

abstract class YASS_Algorithm {
	var $srcData;
	var $srcSync;
	var $destData;
	var $destSync;
		
	abstract function run(
		YASS_DataStore $srcData, YASS_SyncStore $srcSync,
		YASS_DataStore $destData, YASS_SyncStore $destSync,
		YASS_ConflictResolver $conflictResolver
		);
}
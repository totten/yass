<?php

abstract class YASS_Algorithm {
	/**
	 * @var YASS_Replica
	 */
	var $src;
	
	/**
	 * @var YASS_Replica
	 */
	var $dest;
		
	abstract function run(
		YASS_Replica $src,
		YASS_Replica $dest,
		YASS_ConflictResolver $conflictResolver
		);
}
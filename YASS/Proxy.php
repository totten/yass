<?php

class YASS_Proxy {
	
	/**
	 * @var int, arms_interlink site_id
	 */
	var $remoteSite;
	
	/**
	 * @var string, replica name
	 */
	var $remoteReplica;
	
	/**
	 * 
	 */
	public function __construct($remoteSite, $remoteReplica) {
		module_load_include('service.inc', 'yass');
		$this->remoteSite = $remoteSite;
		$this->remoteReplica = $remoteReplica;
	}
	
	public function _proxy() {
		$args = func_get_args();
		$method = array_shift($args);
		array_unshift($args, $this->remoteSite, $method, $this->remoteReplica);
		return call_user_func_array('arms_interlink_call', $args);
	}
	
	/**
	 * Convert items in $array from array() data-structures to object data-structures
	 */
	static function decodeAllInplace($type, &$array) {
		foreach ($array as $key => $item) {
			$object = self::decode($type, $item);
			if (!is_object($object)) {
					_yass_service_error(t('Unrecognized sync data type (!type)', array('!type' => $type)));
			}
			$array[$key] = $object;
		}
	}
	
	
	/**
	 * Convert one item from an array data-structure to an object data-structure
	 *
	 * @param $type string, desired class type
	 * @param $item array
	 * @return object or NULL
	 */
	static function decode($type, $item) {
		if (!is_array($item)) {
			_yass_service_error(t('Failed to parse object (!type): Not an array', array('!type' => $type)));
		}
		switch($type) {
			case 'YASS_Version':
				require_once 'YASS/Version.php';
				return new YASS_Version($item['replicaId'], $item['tick']);
				break;
			case 'YASS_SyncState':
				require_once 'YASS/SyncState.php';
				$modified = self::decode('YASS_Version', $item['modified']);
				$created = self::decode('YASS_Version', $item['created']);
				return new YASS_SyncState($item['entityGuid'], $modified, $created);
				break;
			case 'YASS_Entity':
				require_once 'YASS/Entity.php';
				return new YASS_Entity($item['entityGuid'], $item['entityType'], $item['data']);
				break;
			default:
				_yass_service_error(t('Unrecognized sync data type (!type)', array('!type' => $type)));
		}
	}
	
	/**
	 * Convert items in $array for object-based data-structure to... something suitable for output
	 */
	static function encodeAllInplace($type, &$array) {
		// As used in arms_interlink+jsonrpc_server, json_decode(json_encode(...)...) will
		// turn any objects into untyped arrays. So no explicit step is currently
		// required.
	}
}

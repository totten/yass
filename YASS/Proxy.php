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
     * @param $remoteSite int, interlink site ID
     * @param $remoteReplica string, name of the replica on the remote system
     * @param $localReplica object, reference to the local replica which is being proxied
     */
    public function __construct($remoteSite, $remoteReplica, YASS_Replica $localReplica) {
        module_load_include('service.inc', 'yass');
        $this->remoteSite = $remoteSite;
        $this->remoteReplica = $remoteReplica;
        $this->localReplica = $localReplica;
    }
    
    public function _proxy() {
        $args = func_get_args();
        $method = array_shift($args);
        
        require_once 'YASS/Context.php';
        YASS_Context::push(array(
            '#exportable' => TRUE,
            'proxy.replicaName' => $this->remoteReplica,
            'proxy.effectiveId' => $this->localReplica->id,
        ));
        
        // TODO $contextVars = arms_util_array_keyslice(YASS_Context::getAll(FALSE), $whitelist);
        array_unshift($args, $this->remoteSite, $method, YASS_Context::getAll(FALSE));
        
        // If calling a local web service, we wouldn't want to leak context vars -- only the info from $args[2] should be used
        YASS_Context::push(array(
            '#divider' => TRUE,
        ));
        try {
            $result = call_user_func_array('arms_interlink_call', $args);
            YASS_Context::pop();
            YASS_Context::pop();
            return $result;
        } catch (Exception $e) {
            YASS_Context::pop();
            YASS_Context::pop();
            throw $e;
        }
    }
    
    /**
     * Complement of _proxy which parses the context variables
     *
     * Note: We return $ctx handle to ensure that it's not popped off the stack
     *
     * @return array(0 => YASS_Context, 1 => YASS_Replica)
     */
    static function decodeContext($contextVars) {
        // TODO $contextVars = arms_util_array_keyslice($contextVars, $whitelist);
        require_once 'YASS/Context.php';
        require_once 'YASS/Engine.php';
        $ctx = new YASS_Context($contextVars + array('#exportable' => TRUE));
        $replica = YASS_Engine::singleton()->getReplicaByName(YASS_Context::get('proxy.replicaName'));
        if (is_object($replica)) {
            YASS_Engine::singleton()->setEffectiveReplicaId($replica, YASS_Context::get('proxy.effectiveId'));
        }
        return array($ctx, $replica);
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
                return new YASS_Entity($item['entityGuid'], $item['entityType'], $item['data'], $item['exists']);
                break;
            case 'YASS_Addendum':
                require_once 'YASS/Addendum.php';
                $addendum = new YASS_Addendum();
                $addendum->syncRequired = $item['syncRequired'];
                $addendum->todoTicks = $item['todoTicks'];
                $addendum->todoVersions = $item['todoVersions'];
                foreach ($addendum->todoVersions as $key => $ignore) {
                    self::decodeAllInplace('YASS_Version', $addendum->todoVersions[$key]);
                }
                return $addendum;
            case 'stdClass':
                return (object) $item;
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

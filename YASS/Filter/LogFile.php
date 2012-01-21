<?php

require_once 'YASS/Context.php';
require_once 'YASS/Filter.php';

/**
 * Record a log note about every record that passes through
 */
class YASS_Filter_LogFile extends YASS_Filter {

    /**
     *
     * @param $spec array; keys: 
     *  - file: string, file path
     */
    function __construct($spec) {
        parent::__construct($spec);
        arms_util_include_api('array');
    }
    
    function log($op, $entities, YASS_Replica $replica) {
        $needsHeader = !file_exists($this->spec['file']);
        $fh = fopen($this->spec['file'], 'a');
        if ($needsHeader) {
            fputcsv($fh, array(
                'timestamp',
                'transferId',
                'replica',
                'operation',
                'entityGuid',
                'entityType',
                'entityExists',
                'entityData',
            ));
        }
        $host = defined('DRUSH_URI') ? DRUSH_URI : $_SERVER['HTTP_HOST'];
        foreach ($entities as $entity) {
            fputcsv($fh, array(
                date('Y-m-d H:i:s', arms_util_time()),
                YASS_Context::get('transferId'),
                sprintf('%s@%s <#%s>', $replica->name, $host, $replica->id),
                $op,
                $entity->entityGuid,
                $entity->entityType,
                $entity->exists,
                json_encode($entity->data),
            ));
        }
        fclose($fh);
        if (isset($this->spec['mode']) && fileowner($this->spec['file']) == posix_getuid()) {
            chmod($this->spec['file'], $this->spec['mode']);
        }
    }
    
    function toGlobal(&$entities, YASS_Replica $replica) {
        $this->log('read', $entities, $replica);
    }
    
    function toLocal(&$entities, YASS_Replica $replica) {
        $this->log('write', $entities, $replica);
    }
}

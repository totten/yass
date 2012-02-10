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

require_once 'YASS/Filter.php';

/**
 * Translate foreign keys between local and global ID
 *
 * This filter naively assumes that mappings are already setup. If an FK cannot be translated,
 * then it throws an exception.
 */
class YASS_Filter_FK extends YASS_Filter {

    /**
     *
     * @param $spec array; keys: 
     *  - entityType: string, the type of entity which contains the key
     *  - field: string, the column which stores the fk
     *  - fkType: string, the type of entity referenced by the key
     *  - onUnmatched: string, one of "exception" or "skip"
     */
    function __construct($spec) {
        if (!isset($spec['onUnmatched'])) {
            $spec['onUnmatched'] = 'exception';
        }
        parent::__construct($spec);
    }
    
    function toLocal(&$entities, YASS_Replica $to) {
        $field = $this->spec['field'];
        $entityType = $this->spec['entityType'];
        $fkType = $this->spec['fkType'];
        
        // TODO prefetch FK mappings en masse
        
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if ($entity->entityType == $entityType && isset($entity->data[$field])) {
                list($mappedType, $lid) = $to->mapper->toLocal($entity->data[$field]);
                if ($to->mergeLogs) {
                    require_once 'YASS/Context.php';
                    $newLid = $to->mergeLogs->toValidId($mappedType, $lid);
                    if ($newLid != $lid) {
                        YASS_Context::get('addendum')->tick($entity->entityGuid, $to);
                        $lid = $newLid;
                    }
                }
                if ((!$lid) || ($mappedType != $fkType)) {
                    switch ($this->spec['onUnmatched']) {
                        case 'skip':
                            unset($entity->data[$field]);
                            break;
                        case 'exception':
                        default:
                            throw new Exception(sprintf('Failed to map global=>local FK (replicaId=%s, entityType=%s, field=%s, fkType=%s, fkValue=%s)',
                                $to->id, $entityType, $field, $fkType, $entity->data[$field]
                            ));
                    }
                }
                $entity->data[$field] = $lid;
            }
        }
    }
    
    function toGlobal(&$entities, YASS_Replica $from) {
        $field = $this->spec['field'];
        $entityType = $this->spec['entityType'];
        $fkType = $this->spec['fkType'];
        
        // TODO prefetch FK mappings en masse
        
        foreach ($entities as $entity) {
            if (!$entity->exists) continue;
            if ($entity->entityType == $entityType && isset($entity->data[$field])) {
                $guid = $from->mapper->toGlobal($fkType, $entity->data[$field]);
                if (!$guid) {
                    switch ($this->spec['onUnmatched']) {
                        case 'skip':
                            unset($entity->data[$field]);
                            break;
                        case 'exception':
                        default:
                            throw new Exception(sprintf('Failed to map local=>global FK (replicaId=%s, entityType=%s, field=%s, fkType=%s, fkValue=%s)',
                                $from->id, $entityType, $field, $fkType, $entity->data[$field]
                            ));
                    }
                }
                $entity->data[$field] = $guid;
            }
        }
    }

}

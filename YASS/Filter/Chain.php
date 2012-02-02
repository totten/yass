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

class YASS_Filter_Chain extends YASS_Filter {
    /**
     * @var array(YASS_Filter), sorted by weight ascending
     */
    var $filters;

    function __construct($spec) {
        require_once 'YASS/Context.php';
        
        $this->filters = $spec['filters'];
        usort($this->filters, arms_util_sort_by('weight'));
        unset($spec['filters']);
        
        parent::__construct($spec);
    }
    
    function addFilter(YASS_Filter $filter) {
        $this->filters[] = $filter;
        usort($this->filters, arms_util_sort_by('weight'));
    }

    /**
     * Modify a list of entities, converting local encodings to global encodings
     *
     * @param $entities array(YASS_Entity)
     */
    function toGlobal(&$entities, YASS_Replica $replica) {
        $listener = YASS_Context::get('filterListener'); // YASS_IFilterListener
        if ($listener) $listener->beginToGlobal($entities, $replica);
        foreach ($this->filters as $filter) {
            $filter->toGlobal($entities, $replica);
            if ($listener) $listener->onToGlobal($entities, $replica, $filter);
        }
        if ($listener) $listener->endToGlobal($entities, $replica);
    }
    
    /**
     * Modify a list of entities, converting global encodings to local encodings
     *
     * @param $entities array(YASS_Entity)
     */
    function toLocal(&$entities, YASS_Replica $replica) {
        $listener = YASS_Context::get('filterListener'); // YASS_IFilterListener
        if ($listener) $listener->beginToLocal($entities, $replica);
        foreach (array_reverse($this->filters) as $filter) {
            $filter->toLocal($entities, $replica);
            if ($listener) $listener->onToLocal($entities, $replica, $filter);
        }
        if ($listener) $listener->endToLocal($entities, $replica);
    }
}

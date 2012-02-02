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

require_once 'YASS/IFilter.php';

class YASS_Filter implements YASS_IFilter {

    /**
     * @var int; indicates order of execution. For toGlobal(), filters are called in order of ascending weight; for toLocal(), descending.
     *
     * Ex: $r1->data->getEntities()
     *       => $r1->filters[wgt=1]->toGlobal() 
     *       => $r1->filters[wgt=9]->toGlobal() 
     *       => $r2->filters[wgt=9]->toLocal()
     *       => $r2->filters[wgt=1]->toLocal()
     *       => $r2->data->putEntities()
     */
    var $weight;

    /**
     * @var array, the original specification which produced this filter
     */
    var $spec;

    function __construct($spec) {
        $this->weight = empty($spec['weight']) ? 0 : $spec['weight'];
        $this->spec = $spec;
    }
    
    /**
     * Modify a list of entities, converting local encodings to global encodings
     *
     * @param $entities array(YASS_Entity)
     */
    function toGlobal(&$entities, YASS_Replica $from) {
    }
    
    /**
     * Modify a list of entities, converting global encodings to local encodings
     *
     * @param $entities array(YASS_Entity)
     */
    function toLocal(&$entities, YASS_Replica $to) {
    }

}
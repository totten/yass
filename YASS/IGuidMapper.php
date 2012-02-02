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

/**
 * Translate between globally-unique ID's and replica-local (type,id) pairs.
 *
 * @public
 */
interface YASS_IGuidMapper {
    const NOT_FOUND = -1;

    /**
     * Translate a local (type,id) to a GUID
     *
     * ex: $guid = $mapper->toGlobal($type,$lid);
     *
     * @param $type string
     * @param $lid int
     * @return string or FALSE
     */
    function toGlobal($type, $lid);
    
    /**
     * Translate a GUID to a local (type,id)
     *
     * ex: list($type,$lid) = $mapper->toLocal($guid);
     *
     * @param $guid string
     * @return array(0=>type, 1=>localId) or array(FALSE,FALSE)
     */
    function toLocal($guid);
    
    /**
     * Pre-fetch the mappings for a list of GUIDs
     *
     * @param $guids array(entityGuid)
     * @return array(entityGuid => stdClass('entity_type' => type, 'lid' => localId, 'guid' => entityGuid))
     */
    function loadGlobalIds($guids);
    
    /**
     * Convert a list of local (type,ID)s to GUIDs.
     *
     * Unmapped items do not appear in the result set
     *
     * @param $localids array(type => array(localId))
     * @return array(entityGuid => stdClass('entity_type' => type, 'lid' => localId, 'guid' => entityGuid))
     */
    function loadLocalIds($localids);
    
    /**
     * Add or update mappings between GUIDs and local IDs
     *
     * @param $mappings array(type => array(localId => entityGuid))
     */
    function addMappings($mappings);
    
    /**
     * Permanently erase mappings
     */
    function destroy();
    
    /**
     * Flush any mappings that are cached in memory
     */
    function flush();
    
}

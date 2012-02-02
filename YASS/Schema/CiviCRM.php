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

require_once 'YASS/Schema.php';

class YASS_Schema_CiviCRM extends YASS_Schema {
    static $_ENTITIES = array(
        'civicrm_contact', 'civicrm_address', 'civicrm_phone', 'civicrm_email',
        'civicrm_activity','civicrm_activity_assignment','civicrm_activity_target',
    );
    
    /**
     * @var array(version => YASS_Schema_CiviCRM)
     */
    static $instances;
    
    /**
     * @var array(tableName => array(columName => array('fromCol' => columName, 'toTable' => tableName, 'toCol' => columnName)))
     */
    var $foreignKeys;

    /**
     * @var array($tableName => array(array('fromTable' => $tableName, 'fromCol' => $columnName, 'toTable' => $tableName)))
     */
    var $incomingForeignKeys;
    
    /**
     * @var array(YASS_Filter)
     */
    var $filters;
    
    /**
     * Look up the schema for a given version of CiviCRM
     *
     * FIXME Proper lookup for different
     * @param $version float, CiviCRM version for which we want the schema
     */
    static function instance($version) {
        if (! isset(self::$instances[$version])) {
            $rootXmlFile = drupal_get_path('module', 'civicrm') . '/../xml/schema/Schema.xml';
            self::$instances[$version] = new YASS_Schema_CiviCRM($rootXmlFile, $version);
        }
        return self::$instances[$version];
    }
    
    function __construct($file, $version) {
        arms_util_include_api('fext');
        $this->file = $file;
        $this->version = $version;
        $this->flush();
    }
    
    /**
     * Flush any cached data
     */
    function flush() {
        $this->xml = FALSE;
        $this->filters = FALSE;
        $this->foreignKeys = array();
        $this->incomingForeignKeys = array();
    }
    
    function getEntityTypes() {
        return self::$_ENTITIES;
    }
    
    function getAllEntityTypes() {
        if (is_array($this->allEntityTypes)) {
            return $this->allEntityTypes;
        }
        
        $this->allEntityTypes = array();
        $xmlTables = $this->getXml()->xpath('/database/tables/table');
        foreach ($xmlTables as $xmlTable) {
            if ($this->checkVersion($xmlTable) != 'EXISTS') {
                continue;
            }
            $this->allEntityTypes[] = (string)$xmlTable->name;
        }
        return $this->allEntityTypes;
    }
    
    /**
     * @return SimpleXMLElement
     */
    function getXml() {
        if ($this->xml) {
            return $this->xml;
        }
        if (!is_readable($this->file)) {
            throw new Exception(sprintf("Failed to read XML (%s)", $this->file));
        }
        $dom = new DomDocument( );
        $dom->load( $this->file );
        $dom->xinclude( );
        $this->xml = simplexml_import_dom( $dom );
        return $this->xml;
    }
    
    /**
     * Look up the XML specification for a SQL table
     *
     * @param $tableName string, SQL table
     * @return SimpleXMLElement or FALSE
     */
    function getTableXml($tableName) {
        $items = $this->getXml()->xpath(sprintf('/database/tables/table[name="%s"]', $tableName));
        return (empty($items)) ? FALSE : $items[0];
    }
    
    /**
     * Lookup any fields which are defined in the current version
     *
     * @return TODO
     */
    function getFields($tableName) {
        if (isset($this->fields[$tableName])) {
            return $this->fields[$tableName];
        }
        
        $xmlTable = $this->getTableXml($tableName);
        $this->fields[$tableName] = array();
        foreach ($xmlTable->field as $xmlField) {
            if ($this->checkVersion($xmlField) != 'EXISTS') {
                continue;
            }
            $this->fields[$tableName][ (string)$xmlField->name ] = array(
                'name' => (string) $xmlField->name,
            );
        }
        return $this->fields[$tableName];
        
    }
    
    /**
     * Look up any fields on the given table which store foreign-keys
     *
     * @return array(columName => array('fromCol' => columName, 'toTable' => tableName, 'toCol' => columnName))
     */
    function getForeignKeys($tableName) {
        if (isset($this->foreignKeys[$tableName])) {
            return $this->foreignKeys[$tableName];
        }
        
        $xmlTable = $this->getTableXml($tableName);
        $this->foreignKeys[$tableName] = array();
        foreach ($xmlTable->foreignKey as $xmlFK) {
            if ($this->checkVersion($xmlFK) != 'EXISTS') {
                continue;
            }
            $this->foreignKeys[$tableName][ (string)$xmlFK->name ] = array(
                'fromCol' => (string) $xmlFK->name,
                'toTable' => (string) $xmlFK->table,
                'toCol' => (string) $xmlFK->key,
            );
        }
        return  $this->foreignKeys[$tableName];
    }
    
    /**
     * Look up any related tables to which deletions should cascade
     *
     * @param $tableName string, the table in which an entity is deleted
     * @return array(array('fromTable' => $tableName, 'fromCol' => $columnName, 'toTable' => $tableName, 'toCol' => $columnName, 'onDelete' => $mode))
     *    list of of FKs which point to $tablename
     */
    function getIncomingForeignKeys($tableName) {
        if (isset($this->incomingForeignKeys[$tableName])) {
            return $this->incomingForeignKeys[$tableName];
        }
    
        $xmlFKs = $this->getXml()->xpath(sprintf('/database/tables/table/foreignKey[table="%s"]', $tableName));
        if (empty($xmlFKs)) return array();
        
        $this->incomingForeignKeys[$tableName] = array();
        foreach ($xmlFKs as $xmlFK) {
            if ($this->checkVersion($xmlFK) != 'EXISTS') {
                continue;
            }
            $fromTable = (string) implode('', $xmlFK->xpath('../name'));
            $this->incomingForeignKeys[$tableName][$fromTable . ':' . $xmlFK->name] = array(
                'fromTable' => $fromTable,
                'fromCol' => (string) $xmlFK->name,
                'toTable' => $tableName,
                'toCol' => (string) $xmlFK->key,
                'onDelete' => (string) $xmlFK->onDelete,
            );
        }
        return $this->incomingForeignKeys[$tableName];
    }
    
    /**
     * Lookup any single-value custom-data fields
     *
     * @param $entityType string
     * @return array(customFieldSpec), each customFieldSpec is formatted per arms_util_field
     */
    function getCustomFields($entityType) {
        $entityMap = array( // array(yassEntityType => array(civiEntityType))
            'civicrm_contact' => array('Contact','Individual','Household','Organization'),
            'civicrm_activity' => array('Activity'),
        );
        if (!is_array($entityMap[$entityType])) {
            return array();
        }
        $fields = array();
        foreach ($entityMap[$entityType] as $civiEntityType) {
            $groups = arms_util_groups($civiEntityType);
            if (empty($groups)) continue;
            foreach ($groups as $groupName => $group) {
                if ($groupName == 'engagement') continue;
                if (empty($group['fields'])) { continue;}
                $fields = array_merge($fields, array_values($group['fields']));
                // $fields = $fields + arms_util_array_index(array('_full_name'), $group['fields']);
                // $fields = $fields + arms_util_array_index(array('id'), $group['fields']);
            }
        }
        return $fields;
    }
    
    /**
     * Determine the DAO which represents a given table
     *
     * @return array(0 => fileName|NULL, 1 => className|NULL)
     */
    function getClass($tableName) {
        $xmlTable = $this->getTableXml($tableName);
        if (! $xmlTable) {
            return array(NULL, NULL);
        }
        
        $base = sprintf("%s/DAO/%s", $xmlTable->base, $xmlTable->class);
        $className = strtr($base, '/', '_');
        $file = $base . '.php';
        return array($file, $className);
    }
        
    /**
     * Determine if $node exists in the current schema revision
     *
     * @param $node SimpleXMLElement with optional children, "add" and "drop"
     * @return string 'NOTYET', 'EXISTS', 'DROPPED'
     */
    function checkVersion($node) {
        $add = (float) $node->add;
        $drop = (float) $node->drop;
        
        if ($drop && $this->version >= $drop) {
            return 'DROPPED';
        }
        
        if (! $add) {
            // throw new Exception('checkVersion failed for ' . print_r($node, TRUE));
            return 'EXISTS';
        } elseif ($add <= $this->version) {
            return 'EXISTS';
        } else {
            return 'NOTYET';
        }
    }
    
    /**
     * Get any mappings between local field IDs and global, symbolic field names
     * using fext fields marked with #yass_mapping.
     *
     * @return array(fieldId => name)
     */
    function getFextMappings() {
        $fields = array();
        $defns = arms_util_fext_definitions();
        foreach ($defns as $fextName => $defn) {
            if ($defn['#yass_mapping']) {
                $field = arms_util_field(arms_util_fext_get_field($fextName));
                if (is_array($field)) {
                    $fields[ $field['id'] ] = $defn['#yass_mapping'];
                }
            }
        }
        return $fields;
    }
    
    /**
     * Get a set of local<->global filters for the given release of CiviCRM
     *
     * @return array(YASS_Filter)
     */
    function onBuildFilters(YASS_Replica $replica) {
        if (is_array($this->filters)) {
            return $this->filters;
        }
        
        require_once 'YASS/Filter/CustomFieldName.php';
        require_once 'YASS/Filter/FieldValue.php';
        require_once 'YASS/Filter/FK.php';
        require_once 'YASS/Filter/OptionValue.php';
        require_once 'YASS/Filter/SQLMap.php';
        arms_util_include_api('option');
        
        $this->filters = array();
        $this->filters[] = new YASS_Filter_OptionValue(array(
            'entityType' => 'civicrm_activity',
            'field' => 'activity_type_id',
            'group' => 'activity_type',
            'localFormat' => 'value',
            'globalFormat' => 'name',
        ));
        $this->filters[] = new YASS_Filter_OptionValue(array(
            'entityType' => 'civicrm_contact',
            'field' => 'prefix_id',
            'group' => 'individual_prefix',
            'localFormat' => 'value',
            'globalFormat' => 'name',
        ));
        $this->filters[] = new YASS_Filter_OptionValue(array(
            'entityType' => 'civicrm_contact',
            'field' => 'suffix_id',
            'group' => 'individual_suffix',
            'localFormat' => 'value',
            'globalFormat' => 'name',
        ));
        $this->filters[] = new YASS_Filter_OptionValue(array(
            'entityType' => 'civicrm_contact',
            'field' => 'greeting_type_id',
            'group' => 'greeting_type',
            'localFormat' => 'value',
            'globalFormat' => 'name',
        ));
        $this->filters[] = new YASS_Filter_OptionValue(array(
            'entityType' => 'civicrm_contact',
            'field' => 'gender_id',
            'group' => 'gender',
            'localFormat' => 'value',
            'globalFormat' => 'name',
        ));
        // FIXME when we have proper schema support for Drupal, move this
        $this->filters[] = new YASS_Filter_FK(array(
            'entityType' => 'yass_conflict',
            'field' => 'contact_id',
            'fkType' => 'civicrm_contact',
            'onUnmatched' => 'exception', // 'skip',
        ));
        
        foreach ($this->getEntityTypes() as $entityType) {
            $fields = $this->getFields($entityType);
            $fks = $this->getForeignKeys($entityType);
            $customFields = $this->getCustomFields($entityType);
            
            foreach ($fks as $fk) {
                if ($fk['toCol'] != 'id') {
                    throw new Exception('Non-standard target column');
                }
                if ($entityType == 'civicrm_contact' && $fk['fromCol'] == 'employer_id') {
                    $this->filters[] = new YASS_Filter_FK(array(
                        'entityType' => $entityType,
                        'field' => $fk['fromCol'],
                        'fkType' => $fk['toTable'],
                        'onUnmatched' => 'skip',
                    ));
                    continue;
                }
                switch($fk['toTable']) {
                    case 'civicrm_country':
                        $this->filters[] = new YASS_Filter_SQLMap(array(
                            'entityType' => $entityType,
                            'field' => $fk['fromCol'],
                            'sql' => 'select c.id local, c.iso_code global from civicrm_country c',
                        ));
                        break;
                    case 'civicrm_state_province':
                        $this->filters[] = new YASS_Filter_SQLMap(array(
                            'entityType' => $entityType,
                            'field' => $fk['fromCol'],
                            'sql' => 'select sp.id local, concat(c.iso_code,":",sp.abbreviation) global 
                                from civicrm_country c 
                                inner join civicrm_state_province sp on c.id = sp.country_id',
                        ));
                        break;
                    case 'civicrm_location_type':
                        $this->filters[] = new YASS_Filter_SQLMap(array(
                            'entityType' => $entityType,
                            'field' => $fk['fromCol'],
                            'sql' => 'select t.id local, t.name global from civicrm_location_type t',
                        ));
                        break;
                    default:
                        $this->filters[] = new YASS_Filter_FK(array(
                            'entityType' => $entityType,
                            'field' => $fk['fromCol'],
                            'fkType' => $fk['toTable'],
                        ));
                        break;
                }
            }
            
            // Some, but not all, location_type_id fields are flagged as foreign-keys. This covers the erroneous ones.
            if ($fields['location_type_id'] && !$fks['location_type_id']) {
                $this->filters[] = new YASS_Filter_SQLMap(array(
                    'entityType' => $entityType,
                    'field' => 'location_type_id',
                    'sql' => 'select t.id local, t.name global from civicrm_location_type t',
                ));
            }
            
            foreach ($customFields as $field) {
                // FIXME: Newer versions of Civi add new field types, like 'contact reference'
                $isMultiSelect = in_array($field['html_type'], array('CheckBox','Multi-Select','Multi-Select State/Province','Multi-Select Country'));
                $isSingleSelect = in_array($field['html_type'], array('Select','Radio','Select State/Province','Select Country'));
                
                if ($isMultiSelect) {
                    $this->filters[] = new YASS_Filter_FieldValue(array(
                        'entityType' => $entityType,
                        'field' => $field['_param'],
                        'weight' => -10,
                        'toLocalValue' => 'arms_util_option_implode',
                        'toGlobalValue' => 'arms_util_option_explode',
                    ));
                }
                
                switch($field['data_type']) {
                    case 'Country':
                        $this->filters[] = new YASS_Filter_SQLMap(array(
                            'entityType' => $entityType,
                            'field' => $field['_param'],
                            'sql' => 'select c.id local, c.iso_code global from civicrm_country c',
                            'isMultiple' => $isMultiSelect,
                        ));
                        break;
                    case 'StateProvince':
                        $this->filters[] = new YASS_Filter_SQLMap(array(
                            'entityType' => $entityType,
                            'field' => $field['_param'],
                            'sql' => 'select sp.id local, concat(c.iso_code,":",sp.abbreviation) global 
                                from civicrm_country c 
                                inner join civicrm_state_province sp on c.id = sp.country_id',
                            'isMultiple' => $isMultiSelect,
                        ));
                        break;
                    case 'File':
                        $this->filters[] = new YASS_Filter_FK(array(
                            'entityType' => $entityType,
                            'field' => $field['_param'],
                            'fkType' => 'civicrm_file',
                        ));
                        break;
                    default:
                        /* is the following necessary? or can we assume that custom option-groups are configured well?
                        if ($isMultiSelect || $isSingleSelect) {
                            if ($fields['option_group_id']) {
                                $this->filters[] = new YASS_Filter_OptionValue(array(
                                    'entityType' => $entityType,
                                    'field' => $field['_param'],
                                    'groupId' => $field['option_group_id'],
                                    'localFormat' => 'value',
                                    'globalFormat' => 'name',
                                ));
                            } else {
                                throw new Exception(...);
                            }
                            
                        }
                        */
                        break;
                }
            }
        }
        
        $this->filters[] = new YASS_Filter_CustomFieldName(array(
            'weight' => 10,
            'fields' => $this->getFextMappings(),
        ));
        return $this->filters;
    }
}
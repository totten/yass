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

require_once 'YASS/ReplicaListener.php';
require_once 'YASS/ISchema.php';

class YASS_Schema_CiviCRM extends YASS_ReplicaListener implements YASS_ISchema {
    static $_ENTITIES = array(
        'civicrm_contact', 'civicrm_address', 'civicrm_phone', 'civicrm_email', 'civicrm_website',
        'civicrm_activity','civicrm_activity_assignment','civicrm_activity_target',
    );
    
    /**
     * @var array(entityType => array(fieldName)) List of fields could be removed/ignored if the local schema doesn't support them
     */
    static $_REMOVABLE_FIELDS = array(
        'civicrm_activity' => array(
            'campaign_id',
            'due_date_time',
            'engagement_level',
            'result',
        ),
        'civicrm_contact' => array(
            'addressee_custom',
            'addressee_display',
            'addressee_id',
            'custom_greeting',
            'do_not_sms',
            'email_greeting_custom',
            'email_greeting_display',
            'email_greeting_id',
            'greeting_type_id', // see email_greeting_id and postal_greeting_id
            'home_URL', //  see {civicrm_website}
            'mail_to_household_id',
            'postal_greeting_custom',
            'postal_greeting_display',
            'postal_greeting_id',
            'preferred_language',
        ),
        'civicrm_email' => array(
            'signature_html',
            'signature_text',
        ),
        'civicrm_phone' => array(
            'phone_ext',
        ),
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
    static function instance($rootXmlFile, $version) {
        $key = $version . '::' . $rootXmlFile;
        if (! isset(self::$instances[$key])) {
            self::$instances[$key] = new YASS_Schema_CiviCRM($rootXmlFile, $version);
        }
        return self::$instances[$key];
    }
    
    function __construct($file, $version) {
        arms_util_include_api('fext', 'thinapi');
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
        if (! $this->entityTypes) {
            $this->entityTypes = array_intersect(self::$_ENTITIES, $this->getAllEntityTypes());
        }
        return $this->entityTypes;
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
     * Look up the XML specification for a SQL field
     *
     * @param $tableName string, SQL table
     * @param $fieldName string, SQL column
     * @return SimpleXMLElement or FALSE
     */
    function getFieldXml($tableName, $fieldName) {
        $xmlTable = $this->getTableXml($tableName);
        if (!$xmlTable) return FALSE;
        
        $items = $xmlTable->xpath(sprintf('field[name="%s"]', $fieldName));
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
     * Determine if a SQL field is valid in the current schema
     *
     * TODO: optimize
     *
     * @param $tableName string, SQL table
     * @param $fieldName string, SQL column
     * @return bool
     */
    function hasField($tableName, $fieldName) {
        $xmlField = $this->getFieldXml($tableName, $fieldName);
        return ($xmlField && $this->checkVersion($xmlField) == 'EXISTS') ? TRUE : FALSE;
    }
    
    /**
     * Determine if a SQL table iis valid in the current schema
     *
     * TODO: optimize
     *
     * @param $tableName string, SQL table
     * @return bool
     */
    function hasTable($tableName) {
        $xmlTable = $this->getTableXml($tableName);
        return ($xmlTable && $this->checkVersion($xmlTable) == 'EXISTS') ? TRUE : FALSE;
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
        require_once 'YASS/Filter/IndividualPrefix.php';
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
        $this->filters[] = new YASS_Filter_IndividualPrefix(array(
            'entityType' => 'civicrm_contact',
            'field' => 'prefix_id',
        ));
        $this->filters[] = new YASS_Filter_OptionValue(array(
            'entityType' => 'civicrm_contact',
            'field' => 'suffix_id',
            'group' => 'individual_suffix',
            'localFormat' => 'value',
            'globalFormat' => 'name',
        ));
        if ($this->hasField('civicrm_contact', 'greeting_type_id')) {
            $this->filters[] = new YASS_Filter_OptionValue(array(
                'entityType' => 'civicrm_contact',
                'field' => 'greeting_type_id',
                'group' => 'greeting_type',
                'localFormat' => 'value',
                'globalFormat' => 'name',
            ));
        }
        if ($this->hasField('civicrm_contact', 'email_greeting_id')) {
            $this->filters[] = new YASS_Filter_OptionValue(array(
                'entityType' => 'civicrm_contact',
                'field' => 'email_greeting_id',
                'group' => 'email_greeting',
                'localFormat' => 'value',
                'globalFormat' => 'name',
            ));
        }
        if ($this->hasField('civicrm_contact', 'postal_greeting_id')) {
            $this->filters[] = new YASS_Filter_OptionValue(array(
                'entityType' => 'civicrm_contact',
                'field' => 'postal_greeting_id',
                'group' => 'postal_greeting',
                'localFormat' => 'value',
                'globalFormat' => 'name',
            ));
        }
        if ($this->hasField('civicrm_contact', 'addressee_id')) {
            $this->filters[] = new YASS_Filter_OptionValue(array(
                'entityType' => 'civicrm_contact',
                'field' => 'addressee_id',
                'group' => 'addressee',
                'localFormat' => 'value',
                'globalFormat' => 'name',
            ));
        }
        // Note: civicrm_contact.preferred_language is an OptionValue whose values are naturally portable
        // FIXME: civicrm_activity.result is an OptionValue, but we don't normalize it, and we don't currently use or share "Surveys". To understand its content:
        //   - Use source_record_id to look up the survey which produced the activity
        //   - Use the survey to look up the option group (which is likely to be a volatile option group)
        // FIXME: civicrm_activity.source_record_id is highly dynamic and is not mapped to GUID
        
        $this->filters[] = new YASS_Filter_OptionValue(array(
            'entityType' => 'civicrm_contact',
            'field' => 'gender_id',
            'group' => 'gender',
            'localFormat' => 'value',
            'globalFormat' => 'name',
        ));
        if ($this->hasTable('civicrm_website')) {
            $this->filters[] = new YASS_Filter_OptionValue(array(
                'entityType' => 'civicrm_website',
                'field' => 'website_type_id',
                'group' => 'website_type',
                'localFormat' => 'value',
                'globalFormat' => 'name',
            ));
        }
        
        foreach ($this->getEntityTypes() as $entityType) {
            $fields = $this->getFields($entityType);
            $fks = $this->getForeignKeys($entityType);
            $customFields = arms_util_thinapi_getFields($entityType);
            
            foreach ($fks as $fk) {
                if ($fk['toCol'] != 'id') {
                    throw new Exception('Non-standard target column');
                }
                if ($entityType == 'civicrm_contact' && ($fk['fromCol'] == 'employer_id' || $fk['fromCol'] == 'mail_to_household_id')) {
                    // FIXME In lieu of proper cycle-handling, mark potentially cyclic FKs are skippable
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
            
            
            // TODO: Test across-the-board support for FlexFK
            //if ($fields['entity_table'] && $fields['entity_id']) {
            //    $this->filters[] = new YASS_Filter_FlexFK(array(
            //        'entityType' => $entityType,
            //        'field' => 'entity_id',
            //        'fkTypeField' => 'entity_table',
            //        'onUnmatched' => 'skip',
            //    ));
            //}
            
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
        
        foreach (self::$_REMOVABLE_FIELDS as $tableName => $fieldNames) {
            $filterFieldNames = array();
            foreach ($fieldNames as $fieldName) {
                if (! $this->hasField($tableName, $fieldName)) {
                    $filterFieldNames[] = $fieldName;
                }
            }
            if ($filterFieldNames) {
                require_once 'YASS/Filter/Remove.php';
                $this->filters[] = new YASS_Filter_Remove(array(
                    'entityTypes' => array($tableName),
                    'fields' => $filterFieldNames,
                    'weight' => -1,
                ));
            }
        }
        
        $this->filters[] = new YASS_Filter_CustomFieldName(array(
            'weight' => 10,
            'fields' => $this->getFextMappings(),
        ));
        return $this->filters;
    }
}
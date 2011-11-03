<?php

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
	}
	
	function getEntityTypes() {
		return self::$_ENTITIES;
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
	 * Determine
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
	 * Get a set of local<->global filters for the given release of CiviCRM
	 *
	 * @return array(YASS_Filter)
	 */
	function onBuildFilters(YASS_Replica $replica) {
		if (is_array($this->filters)) {
			return $this->filters;
		}
		
		require_once 'YASS/Filter/FK.php';
		require_once 'YASS/Filter/OptionValue.php';
		require_once 'YASS/Filter/SQLMap.php';
		
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
		
		foreach ($this->getEntityTypes() as $entityType) {
			$fields = $this->getFields($entityType);
			$fks = $this->getForeignKeys($entityType);
			foreach ($fks as $fk) {
				if ($fk['toCol'] != 'id') {
					throw new Exception('Non-standard target column');
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
		}
		return $this->filters;	
	}
}
<?php

/**
 * The "Context" implements dynamic scoping -- it provides a key-value store
 * built on top of a stack of key-value stores. It may be used in two ways:
 *
 * 1. New stackframes can be pushed/popped manually using the push()/pop() functions.
 * Callers using this interface must ensure that pop() is correctly called under
 * all error conditions. (This can be cumbersome considering that PHP lacks
 * try...finally syntax.)
 *
 * 2. Alternatively, one may push a new stackframe by instantiating YASS_Context.
 * The frame will be automatically popped when the object is dereferenced.
 *
 * The second approach provides syntatic sugar but still requires some
 * discipline. See the constructor documentation for details.
 *
 * WARNING: Contextual data does not currently propagate to remote systems when
 * using proxied replicas. This is just another reminder that relying on
 * contextual information will make it harder to mix-and-match components.
 */
class YASS_Context {
	static $_nextScopeId = 1;
	
	/**
	 * @var array(array(key => value)), with most-recent stackframe at the front
	 */
	static $_scopes = array();
	
	/**
	 * Add a new dynamic variable scope.
	 *
	 * When using this interface, you MUST ensure that a corresponding pop()
	 * call is made (under all error conditions).
	 *
	 * @param $values array, optional; list of key-value pairs to include in the new scope
	 */
	static function &push($values = array()) {
		$values['#scopeId'] = self::$_nextScopeId;
		self::$_nextScopeId++;
		array_unshift(self::$_scopes, $values);
		return $values;
	}
	
	/**
	 * Destroy the top-most dynamic variable scope
	 */
	static function pop() {
		return array_shift(self::$_scopes);
	}
	
	/**
	 * Destroy all dynamic variable scopes
	 */
	static function reset() {
		self::$_scopes = array();
	}
	
	/**
	 * Get a copy of the active context
	 *
	 * @param $name string, a name of a variable to retrieve
	 * @return the value of the variable, or NULL
	 */
	static function get($name) {
		foreach (self::$_scopes as $scope) {
			if (array_key_exists($name, $scope)) {
				return $scope[$name];
			}
		}
		return NULL;
	}
	
	/**
	 * Get a list of all values based on the current callstack
	 *
	 * @return array(key => value)
	 */
	static function getAll() {
		$result = array();
		foreach (self::$_scopes as $scope) {
			$result = $result + $scope;
		}
		return $result;
	}
	
	/**
	 * Add a new dynamic variable scope.
	 *
	 * When using this interface, the scope will be automatically destroyed
	 * as soon as the instance is dereferenced. To keep your life simple and
	 * sane, you must:
	 *   - Create only one stackframe at a time (i.e. only one call to
	 *     "new YASS_Context()" within a given function)
	 *   - Keep that instance local to the function (i.e. don't
	 *     store a reference, don't pass the reference
	 *     to other functions)
	 * Other usages may work temporarily but are prone to racing and general
	 * breakage.
	 */
	function __construct($values = array()) {
		$this->values = self::push($values);
	}
	
	function __destruct() {
		if (self::$_scopes[0]['#scopeId'] == $this->values['#scopeId']) {
			self::pop();
		} else {
			throw new Exception('Context integrity exception');
		}
	}
}
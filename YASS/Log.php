<?php

/**
 * Log factory
 *
 * Use YASS_Log::instance() instead of Log::singleton() -- this allows you to disregard the policy
 * considerations of choosing a log medium.
 *
 * The default policy writes out warnings and errors in a file similar to CiviCRM's log. This
 * policy can be overriden by setting constant YASS_LOG_FACTORY to the name of a function. 
 * See defaultFactory() for an example function.
 */
class YASS_Log {

    /**
     * @var array(ident => Log)
     */
    private static $_logs;
    
    /**
     * @var string, file path
     */
    private static $_defaultFile;
    
    /**
     * Get a reference to a log-handler
     *
     * Example $this->log = YASS_Log::instance(get_class());
     * 
     * @param $ident string, e.g. the name of a class which writes to the log
     * @return Log_yassugar
     */
    static function instance($ident) {
        if (!is_array(self::$_logs)) {
            civicrm_initialize(); // update include path to access PEAR's Log class
            require_once 'Log.php';
            self::$_logs = array();
        }
        if (! self::$_logs[$ident]) {
            if (defined('YASS_LOG_FACTORY')) {
                $log = call_user_func(YASS_LOG_FACTORY, $ident);
            } else {
                $log = self::defaultFactory($ident);
            }
            self::$_logs[$ident] = Log::factory('yassugar', '', $ident, array(), $log->_mask);
            self::$_logs[$ident]->addChild($log);
        }
        return self::$_logs[$ident];
    }
    
    /**
     * This factory implements the default log policy
     *
     * @param $ident string, e.g. the name of a class which writes to the log
     * @return Log
     */
    static function defaultFactory($ident) {
        if (!self::$_defaultFile) {
            $config =& CRM_Core_Config::singleton( );
            self::$_defaultFile = sprintf("%s/YASS.log.%s", $config->uploadDir, md5($config->dsn . $config->userFrameworkResourceURL));
        }
        return Log::factory('file', self::$_defaultFile, $ident, array(), PEAR_LOG_WARN);
    }
}

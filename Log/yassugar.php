<?php

require_once 'Log/composite.php';

/**
 * A wrapper which adds richer helper functions and delegates to I/O to other logs.
 *
 * Note that these helper functions will check the mask before doing any real work;
 * this mitigates the performance implication of calling them.
 */
class Log_yassugar extends Log_composite {
    /**
     * Get the current backtrace as a string
     *
     * FIXME: find a way to add this as a helper available on every log instance, e.g. 
     *
     * @return string
     */
    protected static function getStdBacktrace() {
        ob_start();
        debug_print_backtrace();
        $bt = ob_get_contents();
        ob_end_clean();
        $bt = preg_replace ('/^#0\s+' . __FUNCTION__ . "[^\n]*\n/", '', $bt, 1); 
        return $bt;
    }
    
    protected static function getBacktrace($message = FALSE, $start = 0, $end = 10) {
        $backtrace = debug_backtrace( );
        
        $messages = array( );
        if ($message) $messages[] = $message."\n";
        $i = 0;
        foreach ($backtrace as $trace) {
            if ($i >= $start) {
                $messages[] = sprintf("#%-3d %s(...) called at [%s:%s]\n", $i-$start, $trace['function'], $trace['file'], $trace['line']);
            }
            $i++;
            if ($i > $end) {
                break;
            }
        }

       return implode('', $messages);
    }
    
    function debugBacktrace($message = FALSE, $start = 0, $end = 10) {
        if (!$this->_isMasked(PEAR_LOG_DEBUG)) return false;
        $bt = $this->getBacktrace($message, 1+$start, 1+$end);
        return $this->debug($message ? ($message."\n".$bt) : $bt );
    }
    
    function debugSql($query) {
        if (!$this->_isMasked(PEAR_LOG_DEBUG)) return false;
        
        $args = func_get_args();
        $this->debug('SQL Query: "' . implode('" "', $args) . '"');
        $q = call_user_func_array('db_query', $args);
        while ($row = db_fetch_array($q)) {
            $line = 'SQL Row: ';
            foreach ($row as $k=>$v) {
                $line .= $k .'='.$v . ' ';
            }
            $this->debug($line);
        }
    }
    
    function emergf() {
        if (!$this->_isMasked(PEAR_LOG_EMERG)) return false;
        $args = func_get_args();
        return $this->log(call_user_func_array('sprintf', $args), PEAR_LOG_EMERG);
    }

    function alertf() {
        if (!$this->_isMasked(PEAR_LOG_ALERT)) return false;
        $args = func_get_args();
        return $this->log(call_user_func_array('sprintf', $args), PEAR_LOG_ALERT);
    }

    function critf() {
        if (!$this->_isMasked(PEAR_LOG_CRIT)) return false;
        $args = func_get_args();
        return $this->log(call_user_func_array('sprintf', $args), PEAR_LOG_CRIT);
    }

    function errf() {
        if (!$this->_isMasked(PEAR_LOG_ERR)) return false;
        $args = func_get_args();
        return $this->log(call_user_func_array('sprintf', $args), PEAR_LOG_ERR);
    }
    
    function errorf() {
        if (!$this->_isMasked(PEAR_LOG_ERR)) return false;
        $args = func_get_args();
        return $this->log(call_user_func_array('sprintf', $args), PEAR_LOG_ERR);
    }

    function warningf() {
        if (!$this->_isMasked(PEAR_LOG_WARNING)) return false;
        $args = func_get_args();
        return $this->log(call_user_func_array('sprintf', $args), PEAR_LOG_WARNING);
    }

    function noticef() {
        if (!$this->_isMasked(PEAR_LOG_NOTICE)) return false;
        $args = func_get_args();
        return $this->log(call_user_func_array('sprintf', $args), PEAR_LOG_NOTICE);
    }

    function infof() {
        if (!$this->_isMasked(PEAR_LOG_INFO)) return false;
        $args = func_get_args();
        return $this->log(call_user_func_array('sprintf', $args), PEAR_LOG_INFO);
    }

    function debugf() {
        if (!$this->_isMasked(PEAR_LOG_DEBUG)) return false;
        $args = func_get_args();
        return $this->log(call_user_func_array('sprintf', $args), PEAR_LOG_DEBUG);
    }
    
    function error($message) {
        return $this->err($message);
    }
}

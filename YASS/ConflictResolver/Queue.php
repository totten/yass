<?php

require_once 'YASS/ConflictResolver.php';

/**
 * Perform resolutions by delegating to a specific sequence of resolvers. The first conflict
 * is resolved by the first object; the second is resolved by the second object; etc.
 */
class YASS_ConflictResolver_Queue extends YASS_ConflictResolver {
    /**
     * @var array(YASS_IConflictResolver)
     */
    var $resolvers;
    
    function __construct($resolvers) {
        $this->resolvers = $resolvers;
    }
    
    function isEmpty() {
        return empty($this->resolvers);
    }
    
    function resolve(YASS_Conflict $conflict) {
        if (! $this->isEmpty()) {
            $resolver = array_shift($this->resolvers);
        } else {
            require_once 'YASS/ConflictResolver/Exception.php';
            $resolver = new YASS_ConflictResolver_Exception();
        }
        return $resolver->resolveAll(array($conflict));
    }
}

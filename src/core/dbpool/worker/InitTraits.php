<?php
namespace xjryanse\speedy\core\dbpool\worker;

/**
 * 
 */
trait InitTraits{
    protected static $instance = null;

    public static function inst() {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    protected function __construct() {

    }
    
}

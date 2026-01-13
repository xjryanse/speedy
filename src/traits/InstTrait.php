<?php

namespace xjryanse\speedy\traits;

/**
 * 运行环境
 */
trait InstTrait {
    
    protected static $instance = null;

    protected function __construct() {
    }

    public static function inst() {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

}

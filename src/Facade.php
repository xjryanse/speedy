<?php

namespace xjryanse\speedy;

class Facade {

    /**
     * 目标类
     */
    protected static function targetClass() {
        $class = static::class;
        // facade替换为core即得映射类名
        return str_replace('facade', 'core', $class);
    }

    // 调用实际类的方法
    public static function __callStatic($method, $params) {
        // 运行时，即调用子类
        $class  = static::targetClass();
        $inst   = $class::inst();
        return call_user_func_array([$inst, $method], $params);
    }
}

<?php
namespace xjryanse\speedy\core\dbpool\worker;

use xjryanse\speedy\logic\Arrays;
/**
 * 
 */
trait ConfTraits{
    /*
     * 配置转唯一key
     */
    public static function confKey($conf) {
        $keys   = ['hostname','database','charset','hostport','username','password'];
        $array      = Arrays::getByKeys($conf, $keys);
        ksort($array);
        return Arrays::md5($array);
    }
    /**
     * 构造数据库连接字符串
     * @param type $conf
     * @return type
     */
    protected static function dsn($conf) {
        $arr    = [];
        $arr[]  = 'mysql:host=' . $conf['hostname'];
        $arr[]  = 'dbname=' . $conf['database'];
        $arr[]  = 'charset=' . $conf['charset'];
        $arr[]  = 'port=' . $conf['hostport'];
        $arr[]  = 'connect_timeout=30';

        return implode(';', $arr);
    }
}

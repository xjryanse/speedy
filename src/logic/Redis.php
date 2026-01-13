<?php

namespace xjryanse\speedy\logic;

use Redis as RedisSys;

/**
 * 请求
 */
class Redis {
    
    use \xjryanse\speedy\traits\InstTrait;
    
    protected static $redisRaw;

    private $redis = [];
    
    private $default = [
        'host'      =>'127.0.0.1',
        'port'      =>'6379',
        'db'        =>'0',
        'timeout'   =>'0',        
    ];
    
    public function init($conf) {
        $db = Arrays::value($conf, 'db');
        if ($db < 0 || $db > 15) {
            throw new Exception('数据库编号必须在0 - 15之间');
        }

        $host       = Arrays::value($conf, 'host');
        $port       = Arrays::value($conf, 'port');
        $timeout    = Arrays::value($conf, 'timeout');

        $this->redis[$db] = new \Redis();
        $this->redis[$db]->connect($host, $port, $timeout);
        $this->redis[$db]->select($db);
    }

    /**
     * index是0
     * 0：普通缓存
     * 1：会话缓存：替代session;
     * 
     * 2：   （待定）
     * 3：系统配置缓存
     * 4：gps缓存
     * 5-15：（待定）
     * 
     * @param type $index
     * @return type
     */
    public function rdbInst($index = 0){
        if(!$this->redis[$index]){
            $conf       = $this->default;
            $conf['db'] = $index;
            $this->init($conf);
        }
        return $this->redis[$index];
    }

    
    
    
    
    
    /**
     * 20230621:输入测试数据
     */
    public static function logTestData($data, $key = 'testKey') {
        $lData['data'] = $data;
        $lData['time'] = date('Y-m-d H:i:s');

        static::inst()->rdbInst(9)->set($key, json_encode($lData, JSON_UNESCAPED_UNICODE));
    }

    /**
     * 20230621:打印测试数据
     * @param type $key
     */
    public static function dumpTestData($key = 'testKey') {
        $res = static::inst()->rdbInst()->get($key);
        dump($res);
    }
}

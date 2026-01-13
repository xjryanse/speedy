<?php
namespace xjryanse\speedy\core;

use xjryanse\speedy\logic\Arrays;
use Exception;
/**
 * 请求入参
 */
class Redis{
    use \xjryanse\speedy\traits\InstTrait;

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
     * redis连接实例(缓存永)
     * @return type
     */
    public function rdInst() {
        if(!$this->redis[0]){
            $this->init($this->default);
        }
        return $this->redis[0];
    }

    /*
     * 会话用实例：db1;
     */
    public function ssInst() {
        if(!$this->redis[1]){
            $conf       = $this->default;
            $conf['db'] = '1';
            $this->init($conf);
        }
        return $this->redis[1];
    }
    
    /*
     * 系统缓存用实例：db3;
     */
    public function d3Inst() {
        if(!$this->redis[3]){
            $conf       = $this->default;
            $conf['db'] = '3';
            $this->init($conf);
        }
        return $this->redis[3];
    }
    
}
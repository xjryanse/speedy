<?php

namespace xjryanse\speedy\core;

use xjryanse\speedy\core\db\Base as DbBase;
use xjryanse\speedy\facade\Cache;
use xjryanse\speedy\facade\Request;
use xjryanse\speedy\core\db\DbFmp;
use xjryanse\speedy\core\db\DbWorker;
use xjryanse\speedy\logic\Functions;
use xjryanse\speedy\orm\DbOperate;
use Exception;
/**
 * swoole环境下的数据库连接池
 */
class Db {
    //【单例】
    // use \xjryanse\speedy\traits\InstTrait;
    // $uuid为数据库；每个数据库一个实例    
    use \xjryanse\speedy\traits\InstMultiTrait;
    // 20250629
    private $dbConf;
    
    private $dbConnection;
    // 缓存时间（秒）
    private $cacheTime = 0;
    // 20250305：历次执行的sql
    private $sqlArr = [];
    /**
     * 20250209:每张表一个实例
     * @param type $dbSource
     */
    protected function __construct($dbSource = 0) {
        $this->uuid     = $dbSource;
        // 如果是dbEntry；直接取conf；
        if(is_numeric($dbSource)){
            $dbId           = $dbSource;
            $this->dbConf   = DbOperate::idDbConf($dbId);
        } else {
            $this->dbConf   = DbOperate::sourceDbConf($dbSource);
        }

        $env = Request::env();
        if($env == 'phpfpm'){
            $dbFpm      = new DbFmp();
            $this->setCnn($dbFpm);
            $this->connect($this->dbConf);
        } else {
            $dbWorker      = new DbWorker();
            $this->setCnn($dbWorker);
            $this->connect($this->dbConf);
        }
    }
    /**
     * 初始化；
     */
    public function instInit(){
        $this->cacheTime = 0;
    }

    /**
     * 【依赖注入】20250202:设定数据库连接实例
     * fpm环境下使用DbFpm; swoole环境下使用DbSwoole;
     * @param DbBase $dbConnection
     */
    protected function setCnn(DbBase $dbConnection) {
        $this->dbConnection = $dbConnection;
    }
    /**
     * 建立数据库连接
     * @param array $config 数据库连接配置
     * @return type
     */
    protected function connect($config){
        return $this->dbConnection->connect($config);
    }
    /**
     * 20250202：查询
     * @param string $query
     * @return type
     */
    public function query(string $query, array $bind = []){
        if ($this->cacheTime) {
            $cacheKey = $this->cacheKey($query);
            $res = Cache::funcGet( $cacheKey, function() use ($query, $bind){
                return $this->dbConnection->query($query, $bind);
            }, $this->cacheTime);
            // $this->cacheTime = 0;
            return $res;
        }
        // 20250305：记录历次执行sql
        $res = Functions::execWithTime(function() use ($query, $bind){
            return $this->dbConnection->query($query, $bind);
        });
        $this->sqlArr[] = '['.$res['timeDiff'].']'. $query;
        return $res['res'];
    }
    
    public function find(string $query){
        if ($this->cacheTime) {
            // 单条的缓存key加个F
            $cacheKey = $this->cacheKey($query).'_F';
            $res = Cache::funcGet( $cacheKey, function() use ($query){
                return $this->dbConnection->find($query);
            },$this->cacheTime);
            // $this->cacheTime = 0;
            return $res;
        }
        $res = Functions::execWithTime(function() use ($query){
            return $this->dbConnection->find($query);
        });
        $this->sqlArr[] = '['.$res['timeDiff'].']'.$query;
        return $res['res'];
    }
    /**
     * 执行数据库操作
     * @param string $query
     * @return type
     */
    public function execute(string $query){
        $res = Functions::execWithTime(function() use ($query){
            return $this->dbConnection->execute($query);
        });
        $this->sqlArr[] = '['.$res['timeDiff'].']'. $query;
        return $res['res'];
    }
    
    /**
     * 是否在事务中
     */
    public function inTransaction() {
        return $this->dbConnection->inTransaction();
    }
    
    /**
     * 开启事务
     * @return bool 返回开启事务的结果
     */
    public function startTrans() {
        return $this->dbConnection->startTrans();
    }
    
    public function rollback() {
        return $this->dbConnection->rollback();
    }
    /**
     * 提交事务
     * @return bool 返回提交事务的结果
     */
    public function commit() {
        return $this->dbConnection->commit();
    }
    
    /*缓存相关***********************/
    // cache 方法，用于开启缓存并设置缓存时间
    public function cache($cacheTime = 3600) {
        $this->cacheTime = $cacheTime;
        return $this; // 返回 $this 以支持链式调用
    }
    
    private function cacheKey($query) {
        // dump(md5($query).':='.$query);
        return 'DB_Q:'.md5($query);
    }
    // 20250305
    public function getSqlArr(){
        return $this->sqlArr;
    }
    // 20250629
    public function getDbConf(){
        return $this->dbConf;
    }
    /**
     * 20250805:连接数
     * @return type
     */
    public function cnnCount(){
        return $this->dbConnection->cnnCount();
    }
    
}

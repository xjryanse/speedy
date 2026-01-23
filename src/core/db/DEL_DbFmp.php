<?php

namespace xjryanse\speedy\core\db;

use PDO;
use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\logic\Network;
/**
 * 数据库连接池(传统fpm废弃，统一用DbWorker替代，在fpm也可以使用)
 */
class DbFmp extends Base {
    // 单例
    // use \xjryanse\speedy\traits\InstTrait;
    // 数据库连接实例
    private $cnn ;
    // 数据库连接配置
    private $dbConfig = [] ;
    /**
     * 配置
     * @param type $name
     * @return type
     */
    public function getConfig($name){
        return $name 
                ? Arrays::value($this->dbConfig, $name) 
                : $this->dbConfig;
    }

    /**
     * 20250131:dsn
     * @return type
     */
    private function dsn() {
        $host           = Arrays::value($this->dbConfig, 'realHost') ?: Arrays::value($this->dbConfig, 'hostname');

        $arr    = [];
        $arr[]  = 'mysql:host=' . $host;
        $arr[]  = 'dbname=' . $this->dbConfig['database'];
        $arr[]  = 'charset=' . $this->dbConfig['charset'];
        $arr[]  = 'port=' . $this->dbConfig['hostport'];
        $arr[]  = 'connect_timeout=30';

        return implode(';', $arr);
    }
    
    // 抽象方法：连接数据库
    public function connect(array $config){
        $this->dbConfig = $config;
        $options = [
            // 开启异常模式
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // 设置查询超时时间为 5 秒
            PDO::ATTR_TIMEOUT => 5, 
            // 自动转为string
            PDO::ATTR_STRINGIFY_FETCHES => false,
            // 禁用预查询模拟
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        // $cnnStime = time();
        
        $pdo = new PDO($this->dsn(), $this->dbConfig['username'], $this->dbConfig['password'], $options);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // $cnnEtime = time();
        
        
        $this->cnn = $pdo;
    }

    
    
    
    // 抽象方法：执行查询
    public function query(string $query, array $bind =[]){
        //2026年1月19日：改写支持参数绑定
        $stmt = $this->cnn->prepare($query);
        // 2. 核心修改：绑定参数并执行SQL，直接传入绑定数组即可
        // 支持两种占位符：命名占位符 :id / 问号占位符 ? ，自动兼容
        $stmt->execute($bind);
        // 3. 保留你原有的返回格式：二维关联数组，业务层无感知
        $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);

//        $stmt       = $this->cnn->query($query);
//        // 返回全部
//        $res = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $res;
    }

    // 抽象方法：查询取一条
    public function find(string $query){
        $stmt   = $this->cnn->query($query);
        // 返回一条
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    /**
     * 执行sql
     * @param string $query
     * @return type
     */
    public function execute(string $query){
        // 执行sql，返回影响行数
        return $this->cnn->exec($query);
    }
    
    // 抽象方法：关闭连接
    public function close(){
        
    }
    /**
     * 判断是否在事务中
     */
    public function inTransaction() {
        return $this->cnn->inTransaction();
    }
    
    /**
     * 开启事务
     * @return bool 开启事务是否成功
     */
    public function startTrans() {
        return $this->cnn->beginTransaction();
    }
    /**
     * 提交事务
     * @return bool 提交事务是否成功
     */
    public function commit() {
        return $this->cnn->commit();
    }
    /**
     * 回滚事务
     * @return bool 回滚事务是否成功
     */
    public function rollback() {
        return $this->cnn->rollBack();
    }

    public function cnnCount(){
        return 1;
    }    
}

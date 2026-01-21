<?php

namespace xjryanse\speedy\core\db;

use PDO;
use PDOException;
use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\core\dbpool\DbPoolWorker;

/**
 * workerman环境下的数据库连接池
 */
class DbWorker extends Base {
    // 存储数据库连接的数组
    // 数据库连接配置
    private $dbConfig = [] ;
    private $poolInst;
    private $cnn ;
    
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

        
    // 抽象方法：连接数据库
    public function connect(array $config){
        $this->dbConfig = $config;
        $this->poolInst = DbPoolWorker::inst();
    }
    
    protected function getCnn(){
        if(!$this->cnn){
            // 数据库连接池
            $this->cnn = $this->poolInst->getCnn($this->dbConfig);
        }
        return $this->cnn;
    }
    
    protected function putCnn($cnn){
        $this->cnn = null;
        $this->poolInst->putCnn($cnn, $this->dbConfig);
    }
    
    
    // 抽象方法：执行查询
    public function query(string $query, array $bind =[]){
        $cnn        = $this->getCnn();
        $stmt       = $cnn['pdo']->prepare($query);
        // $stmt       = $cnn['pdo']->query($query);
        $stmt->execute($bind);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $this->putCnn($cnn);
        return $result;
    }
    
    // 抽象方法：查询取一条
    public function find(string $query){
        $cnn    = $this->getCnn();
        $stmt   = $cnn['pdo']->query($query);
        // 返回一条
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->putCnn($cnn);

        return $result;
    }
    
    public function execute(string $query){
        $cnn = $this->getCnn();
        // throw new \Exception('调试中');
        // 执行sql，返回影响行数
        $result = $cnn['pdo']->exec($query);
        $this->putCnn($cnn);

        return $result;
    }

    // 抽象方法：关闭连接
    public function close(){
        
    }
    
    /**
     * 开启事务
     * @return bool 开启事务是否成功
     */
    public function startTrans() {
        $cnn    = $this->getCnn();
        $result = $cnn['pdo']->beginTransaction();
        return $result;
    }
    /**
     * 提交事务
     * @return bool 提交事务是否成功
     */
    public function commit() {
        $cnn = $this->getCnn();
        return $cnn['pdo']->commit();
    }
    /**
     * 回滚事务
     * @return bool 回滚事务是否成功
     */
    public function rollback() {

    }
    
    /**
     * 检查是否在事务中
     * @return bool
     */
    public function inTransaction() {

    }
    
    /**
     * 检查连接是否有效
     * @return bool
     */
    public function ping() {
        try {
            $cnn = $this->getCnn();
            $result = $cnn['pdo']->query('SELECT 1')->fetch();
            $this->putCnn($cnn);
            return !empty($result);
        } catch (PDOException $e) {
            return false;
        }
    }
    /**
     * 当前池中有多少连接
     */
    public function cnnCount(){
        return $this->pool->count();
    }
    
    /**
     * 析构析构函数：清理资源
     */
    public function __destruct() {

    }
}

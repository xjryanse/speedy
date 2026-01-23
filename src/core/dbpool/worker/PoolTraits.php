<?php
namespace xjryanse\speedy\core\dbpool\worker;

use PDO;
use PDOException;
use Exception;
/**
 * 池管理
 */
trait PoolTraits{
    // 20250202:标记连接实例使用中
    private $pools;
    /**
     * 创建新连接并入池
     */
    public function newCnnToPool($config){
        $cnn = $this->newCnn($config);
        return $this->putCnn($cnn, $config);
    }
    /**
     * 创建新连接
     * @param type $config
     * @return array
     */
    protected function newCnn($config) {
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

        $pdo      = new PDO(static::dsn($config), $config['username'], $config['password'], $options);
        // $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        return [
            'pdo'           => $pdo,
            'created_at'    => date('Y-m-d H:i:s'),
            'last_used'     => date('Y-m-d H:i:s'),
            'id'            => uniqid('db_', true), // 唯一标识
            'status'        => 'idle' // idle:空闲, using:使用中
        ];
    }
    
    // 从连接池中获取一个连接
    public function getCnn($config) {
        $dbKey  = static::confKey($config);
        // 处理池中无连接的情况:直接创建一个新连接返回;
        if (!isset($this->pools[$dbKey]) || !$this->pools[$dbKey]) {
            return $this->newCnn($config);
        }
        if ($this->pools[$dbKey]->isEmpty()) {
            throw new Exception('连接池为空，请联系开发排查');
        }
        // echo '连接池取出\n\r';
        // 从池中提取一个数据库连接
        $cnn = $this->pools[$dbKey]->dequeue();
        // 验证连接是否有效（可选但推荐）
        try {
            $cnn['pdo']->query('SELECT 1');
            return $cnn;
        } catch (PDOException $e) {
            // 连接失效，销毁旧连接并创建新连接
            unset($cnn);
            return $this->newCnn($config);
        }
    }

    /**
     * 将连接放回连接池
     * @param type $cnn
     * @param type $config
     * @return type
     */
    public function putCnn($cnn, $config) {
        $dbKey  = static::confKey($config);
        if($this->pools && isset($this->pools[$dbKey]) && $this->pools[$dbKey] && $this->pools[$dbKey]->count() >= 5){
            // 超过5个就释放连接
            $this->releaseCnn($cnn);
            return false;
        }

        $cnn['last_used'] = date('Y-m-d H:i:s');
        if (!isset($this->pools[$dbKey])) {
            $this->pools[$dbKey] = new \SplQueue();
        }
        return $this->pools[$dbKey]->enqueue($cnn);
    }
    /**
     * 20251011:释放连接
     * @param type $cnn
     * @return type
     */
    private function releaseCnn($cnn) {
        // 检查是否是有效的连接数组
        $pdo = $cnn['pdo'];
        // 1. 显式关闭PDO连接（不同数据库驱动可能有差异）
        if ($pdo instanceof PDO) {
            try {
                // MySQL可以通过执行关闭命令
                $pdo->exec('KILL CONNECTION_ID()');
            } catch (\PDOException $e) {
                // 忽略关闭时的异常（可能连接已失效）
            }
            // 2. 销毁PDO对象（触发析构函数释放资源）
            $cnn['pdo'] = null;
            unset($pdo);
        }
        // 3. 清除连接相关的元数据
        unset($cnn);
    }
    
    
}

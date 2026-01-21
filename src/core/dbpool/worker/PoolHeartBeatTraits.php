<?php
namespace xjryanse\speedy\core\dbpool\worker;

use PDO;
use PDOException;
use Exception;
use Workerman\Timer;
/**
 * 池管理
 */
trait PoolHeartBeatTraits{
    // 心跳检测定时器ID
    private $heartbeatTimer = null;
    // 心跳检测间隔(秒)，建议小于数据库wait_timeout(默认8小时)
    private $heartbeatInterval = 60; // 5分钟

    /**
     * 初始化心跳检测（单例模式）
     */
    public function initPool() {
        if ($this->heartbeatTimer){
            return false;
        }
        
        $this->heartbeatTimer = Timer::add($this->heartbeatInterval, function() {
            $this->doHeartbeatCheck();
        });
        
        $this->log("[连接池心跳] 已启动，间隔: {$this->heartbeatInterval}秒");
    }

    /**
     * 执行心跳检测
     */
    private function doHeartbeatCheck() {
        $this->log("\n[心跳检测] 开始执行");
        
        // 无连接池或格式错误直接返回
        if (empty($this->pools) || !is_array($this->pools)) {
            $this->log("[心跳检测] 无有效连接池，跳过");
            return;
        }

        foreach ($this->pools as $dbKey => &$queue) {
            // 跳过非队列或空队列
            if (!$queue instanceof \SplQueue || $queue->isEmpty()) continue;

            $total = $queue->count();
            $valid = $invalid = 0;
            $newQueue = new \SplQueue();

            // 逐个检查连接
            while (!$queue->isEmpty()) {
                $cnn = $queue->dequeue();
                if ($this->checkConnectionAlive($cnn)) {
                    $newQueue->enqueue($cnn);
                    $valid++;
                } else {
                    $this->releaseCnn($cnn);
                    $invalid++;
                }
            }

            $this->pools[$dbKey] = $newQueue;
            $this->log("[{$dbKey}] 检测完成 - 总:{$total}, 有效:{$valid}, 无效:{$invalid}");
        }

        $this->log("[心跳检测] 执行结束");
    }

    /**
     * 检查连接有效性
     */
    private function checkConnectionAlive(&$cnn) {
        // 基础验证
        if (!isset($cnn['pdo'], $cnn['id']) || !$cnn['pdo'] instanceof PDO) {
            $this->log("[连接{$cnn['id']}] 无效: 非PDO对象");
            return false;
        }
        // 如果近期已经有过查询动作，就不用心跳了
        if($cnn['last_used'] && time() - strtotime($cnn['last_used']) <$this->heartbeatInterval){
            return true;
        }

        // 执行心跳SQL
        try {
            $start  = microtime(true);
            $result = $cnn['pdo']->query('SELECT 1')->fetchColumn();
            $cost   = round(microtime(true) - $start, 4);

            if ($result === 1) {
                $cnn['last_heartbeat'] = date('Y-m-d H:i:s');
                $this->log("[连接{$cnn['id']}] 心跳成功 (耗时:{$cost}s)");
                return true;
            }

            $this->log("[连接{$cnn['id']}] 心跳失败: 结果异常");
            return false;

        } catch (PDOException $e) {
            $this->log("[连接{$cnn['id']}] 心跳失败: {$e->getMessage()} ({$e->getCode()})");
            return false;
        }
    }

    /**
     * 简化日志输出
     */
    private function log($message) {
        // echo "[".date('Y-m-d H:i:s')."] {$message}\n";
    }
    
}

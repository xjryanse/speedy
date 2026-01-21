<?php
namespace xjryanse\speedy\core\dbpool\worker;

/**
 * 池统计
 */
trait PoolStaticsTraits{

    /**
     * 统计信息：连接数
     */
    public function cnnStatics(){
        $counts = [];
        foreach ($this->pools as $dbKey => $queue) {
            $counts[$dbKey] = $queue->count();
        }
        return $counts;
    }
    /**
     * 连接列表，一个数组
     */
    public function cnnList(){
        $arr = [];
        foreach ($this->pools as $dbKey => $queue) {
            // 连接队列转数组
            $connections = $queue ? iterator_to_array($queue) : [];
            foreach($connections as &$v){
                $v['dbKey'] = $dbKey;
                $arr[] = $v;
            }
        }
        return $arr;
    }
    
    
}

<?php

namespace xjryanse\speedy\orm\baseTraits;

use xjryanse\speedy\facade\DbOrm;
use xjryanse\speedy\facade\Cache;
use xjryanse\speedy\facade\Redis;
use xjryanse\speedy\facade\Request;
use orm\system\SystemErrorLog;
use xjryanse\speedy\orm\DbOperate;

/**
 * 带redis缓存的模型复用 主模型复用
 */
trait RedisModelTrait {
    
    protected static function redisKey() {
        // 20230717识别当前连接的数据库
        $dbMd5 = md5(Request::host());
        return static::class.$dbMd5;
    }

    /**
     * 高频数据暂存redis
     * @param type $data
     * @return type
     */
    public static function redisLog($data) {
        // 有哪些类用到了redis暂存数据
        $redisClasses = Cache::get('redisLogClasses') ?: [];
        if (!in_array(static::class, $redisClasses)) {
            $redisClasses[] = static::class;

            Cache::set('redisLogClasses', array_unique($redisClasses));
        }
        // 20221026
        $data['company_id']     = session(SESSION_COMPANY_ID);
        $data['creater']        = session(SESSION_USER_ID);
        $data['create_time']    = date('Y-m-d H:i:s');
        $data['source']         = Request::header('source');
        // $key = $this->chatKeyGenerate( $chatWithId );
        $key = static::redisKey();
        $res = Redis::rdInst()->lpush($key, json_encode($data, JSON_UNESCAPED_UNICODE));
        return $res ? $data : [];
    }

    /**
     * redis搬到数据库
     */
    public static function redisToDb() {
        $key = static::redisKey();
        $index = 1;
        //每次只取100条
        $data = [];
        while ($index <= 50) {
            $tmpData = Redis::rdInst()->rpop($key);
            //只处理json格式的数据包
            if ($tmpData && is_array(json_decode($tmpData, true))) {
                $data[] = json_decode($tmpData, true);
            }
            $index++;
        }
        if (!$data) {
            return false;
        }
        
        static::saveAllRam($data);
        DbOperate::dealGlobal();
/*
        // Debug::dump(static::getTable().'的redisToDb数据', $data);
        //开事务保存，保存失败数据恢复redis
        $dbSource = static::dbSource();
        DbOrm::startTrans([$dbSource]);
        try {
            static::saveAllRam($data);
            // 提交事务
            DbOrm::commit([$dbSource]);
        } catch (\Exception $e) {
            // 回滚事务
            DbOrm::rollback([$dbSource]);
            // 20230717:错误信息记录
            SystemErrorLog::exceptionLog($e);
            //数据恢复到redis
            while (count($data)) {
                $ttData = array_pop($data);
                //推回redis
                Redis::rdInst()->rpush($key.'_BACK', json_encode($ttData, JSON_UNESCAPED_UNICODE));
            }
        }
 */
    }

    /**
     * ****************************************************
     * @return type
     */
    protected static function redisTodoKey() {
        return static::class . '_TODO';
    }

    /**
     * 20230415:redis 缓存时间key
     * @return type
     */
    protected static function redisTodoTimeKey() {
        return static::class . '_TODOTime';
    }

    /**
     * 有缓存时间，说明没被清理
     * @return type
     */
    public static function redisHasTodoTime() {
        return Redis::rdInst()->get(static::redisTodoTimeKey());
    }

    /*
     * 20230415:添加redis待处理任务
     */

    protected static function redisTodoAdd($data) {
        // 有哪些类用到了redis暂存数据
        $redisTodoClasses = Cache::get('redisTodoClasses') ?: [];
        if (!in_array(static::class, $redisTodoClasses)) {
            $redisTodoClasses[] = static::class;
            Cache::set('redisTodoClasses', $redisTodoClasses);
        }
        Redis::rdInst()->set(static::redisTodoTimeKey(), time());
        // $key = $this->chatKeyGenerate( $chatWithId );
        $key = static::redisTodoKey();
        $res = Redis::rdInst()->lpush($key, json_encode($data, JSON_UNESCAPED_UNICODE));
        return $res ? $data : [];
    }

    /**
     * 20230415：待处理数据批量暂存
     * @param type $dataArr
     */
    protected static function redisTodoAddBatch($dataArr) {
        // 有哪些类用到了redis暂存数据
        Redis::rdInst()->set(static::redisTodoTimeKey(), time());
        foreach ($dataArr as $data) {
            static::redisTodoAdd($data);
        }
        return $dataArr;
    }

    /**
     * 
     */
    public static function redisTodoList() {
        $key = static::redisTodoKey();
        $index = 1;
        //每次只取100条
        $data = [];
        while ($index <= 100) {
            $tmpData = Redis::rdInst()->rpop($key);
            //只处理json格式的数据包
            if ($tmpData && is_array(json_decode($tmpData, true))) {
                $data[] = json_decode($tmpData, true);
            }
            $index++;
        }
        return $data;
    }
}

<?php

namespace xjryanse\speedy\core;

use xjryanse\speedy\facade\Redis;
use xjryanse\speedy\logic\Strings;
use xjryanse\speedy\facade\Request;

/**
 * 缓存
 */
class Cache {

    use \xjryanse\speedy\traits\InstTrait;

    /**
     * 20250226:key前缀，用于区分
     */
    protected static function preFix() {
        $host = Request::host();
        return md5($host);
    }

    /**
     * 设置缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $expire 缓存过期时间（秒）
     * @return bool
     */
    public function set($key, $value, $expire = 0) {
        $keyN = self::preFix() . $key;
        $cV = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
        if ($expire > 0) {
            return Redis::rdInst()->setex($keyN, $expire, $cV);
        }
        return Redis::rdInst()->set($keyN, $cV);
    }

    /**
     * 20250302:某个key状态
     * @param type $key
     * -2:key不存在已删除；-1:key存在，么有过期时间；其他:key存在且有效
     */
    public function keyState($key) {
        $keyN = self::preFix() . $key;
        return Redis::rdInst()->ttl($keyN);
    }

    /**
     * 获取缓存
     * @param string $key 缓存键
     * @return mixed
     */
    public function get($key) {
        $keyN = self::preFix() . $key;
        $cV = Redis::rdInst()->get($keyN);
        if (Strings::isJson($cV)) {
            $cV = json_decode($cV, JSON_UNESCAPED_UNICODE);
        }
        return $cV;
    }

    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return int
     */
    public function rm($key) {
        $keyN = self::preFix() . $key;
        return Redis::rdInst()->del($keyN);
    }

    /**
     * 检查缓存是否存在
     * @param string $key 缓存键
     * @return bool
     */
    public function exists($key) {
        $keyN = self::preFix() . $key;
        return Redis::rdInst()->exists($keyN);
    }

    /**
     * 20250224:有缓存取缓存，无缓存闭包算
     */
    public function funcGet($key, $func, $expire = null) {
        $cV = $this->get($key);
        if (!$cV && $this->keyState($key) == -2) {
            $cV = $func();
            $this->set($key, $cV, $expire);
        }
        return $cV;
    }

    /**
     * 清除全部缓存
     * @param bool $flushAll 是否清空所有数据库的缓存，默认为 false，即只清空当前数据库
     * @return bool
     */
    public function clearAll() {
        // return Redis::rdInst()->flushDB();
        // 20250826：改
        $prefix = self::preFix();
        $redis = Redis::rdInst();
        
        // 使用SCAN命令安全遍历所有key，避免KEYS命令在大数据量时阻塞Redis
        $iterator   = null;
        $count      = 1000; // 每次扫描的数量
        do {
            // 扫描带有本系统前缀的key
            $keys = $redis->scan($iterator, $prefix . '*', $count);
            
            if (!empty($keys)) {
                // 批量删除找到的key
                $redis->del($keys);
            }
        } while ($iterator > 0);
        
        return true;
    }

    /**
     * 生成缓存key
     */
    public static function cacheKey() {
        $args = func_get_args();
        $jsonStr = json_encode($args);
        return md5($jsonStr);
    }

    /**
     * 清除缓存，不清某些key
     */
    public function clearExcept($keys = []) {
        $arr = [];
        //【1】先存起来
        foreach ($keys as $k) {
            $arr[$k] = $this->get($k);
        }
        //【2】清缓存
        $this->clearAll();
        //【3】回写
        foreach ($arr as $kk => $vv) {
            $this->set($kk, $vv);
        }
    }
}

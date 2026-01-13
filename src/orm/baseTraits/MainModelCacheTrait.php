<?php

namespace xjryanse\speedy\orm\baseTraits;

use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\logic\Runtime;
use xjryanse\speedy\facade\Cache;

/**
 * 一些缓存的复用
 */
trait MainModelCacheTrait {
    /**
     * 重新载入数据到缓存文件
     */
    public static function reloadCacheToFile() {
        // $mainModel = static::inst();
        $mainModel = static::class;
        if (property_exists($mainModel, 'cacheToFile') && $mainModel::$cacheToFile) {
            $tableName = static::getTable();
            Runtime::tableCacheDel($tableName);
            // Runtime::tableFullCache($tableName);
        }
    }
    
    
    
    
    
    
    
    
    
    
    
    /**
     * 缓存get数据的key值
     */
    protected function cacheGetKey() {
        $tableName = static::inst()->getRawTable();
        return 'mainModelGet_' . $tableName . '-' . $this->uuid;
    }

    /**
     * 数据统计的cache值
     * @return type
     */
    protected static function cacheCountKey() {
        $tableName  = static::getTable();
        $dbSource   = static::dbSource();
        return 'mainModelCount_' . $tableName . $dbSource;
    }
    
    /**
     * 20230709:带缓存查询统计数据
     */
    public static function countCache($conAll) {
        $keyMd5 = md5(json_encode($conAll));
        $countArr = Cache::get(static::cacheCountKey()) ?: [];
        if (!isset($countArr[$keyMd5])) {
            $count = static::inst()->where($conAll)->count(1);
            $countArr[$keyMd5] = $count;
            Cache::set(static::cacheCountKey(), $countArr);
        }
        return Arrays::value($countArr, $keyMd5, 0);
    }

    /**
     * 20230709:统计数据清除
     */
    public static function countCacheClear() {
        $key = static::cacheCountKey();
        Cache::rm($key);
    }
    
    
    /*
     * 20230729:数据缓存清理
     * 一般用于增删改之后清理缓存数据
     */
    protected static function dataCacheClear(){
        //2022-12-15:增加静态配置清缓存
        if (method_exists(static::class, 'staticCacheClear')) {
            // 20250306加companyId
            $companyId = session(SESSION_COMPANY_ID);
            static::staticCacheClear($companyId);
        }
        //20230729:全量缓存表重载
        static::reloadCacheToFile();
        // 20230709:清除统计数据缓存
        static::countCacheClear();
    }
}

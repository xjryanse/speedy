<?php

namespace xjryanse\speedy\orm\baseTraits;

use xjryanse\speedy\facade\Cache;
use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\logic\Arrays2d;
use Exception;
/**
 * 运行环境
 */
trait StaticModelTrait {
    // 20230619:增加静态变量，提升性能
    protected static $staticListsAll = [];
    
    public static function cacheMt(){
        if (property_exists(static::class, 'cacheMt') && static::$cacheMt = 'apcu') {
            // apcu缓存
            return '\speedy\facade\Cache';
        }
        // redis缓存
        return '\speedy\facade\Cache';
    }
    
    /**
     * 写入key，方便删
     * @param type $key
     */
    private static function staticCacheKeysSet($companyId, $key){
        $keyName        = static::staticBaseCacheKey($companyId).'_KEYS';
        $keyNameArr     = static::cacheMt()::get($keyName) ? : [];
        $keyNameArr[]   = $key;
        // dump($keyNameArr);
        static::cacheMt()::set($keyName, array_unique($keyNameArr));
    }
    /**
     * 
     */
    public static function staticConFind($con = [], $companyId = ''){
        // 20250302:数据库源
        $keyMd5     = md5(json_encode($con));
        $key        = static::staticBaseCacheKey($companyId).'_Find'.$keyMd5;
        static::staticCacheKeysSet($companyId, $key);        
        return static::cacheMt()::funcGet( $key, function() use ($con, $companyId){
            $listsAll = static::staticListsAll($companyId);
            if(!$listsAll){
                return [];
            }
            foreach($listsAll as $data){
                if(Arrays::isConMatch($data, $con)){
                    return $data;
                }
            }
            return [];
        });
    }
    /**
     * 
     * @useFul 1
     * @param type $companyId
     * @return type
     */
    public static function staticBaseCacheKey($companyId = ''){
        if(!$companyId){
            $companyId = session(SESSION_COMPANY_ID);
        }
        $dbSource   = static::dbSource();
        $tableName  = static::getTable();
        return $tableName.'_staticListsAllDb'.$dbSource.$companyId;
    }
    
    public static function staticListsAll($companyId = ''){
        $res = static::staticListsAllDb($companyId);
        // 20231223
        if (method_exists(static::class, 'comCateLevelCommCon')) {
            $comCateLevelCon = static::comCateLevelCommCon();
            $arr = static::lists($comCateLevelCon);
            // $arr = static::comCateLevelListArr();
            $res = array_merge($arr,$res);
        }

        // 20230619:存静态变量
        static::$staticListsAll = $res ? static::dataDealAttr($res) : $res;
        return static::$staticListsAll ;
    }
    
    public static function staticListsAllDb($companyId = ''){
        $key        = static::staticBaseCacheKey($companyId);
        // 20250321
        static::staticCacheKeysSet($companyId, $key);       
        $res = static::cacheMt()::funcGet( $key, function() use ($companyId){
            $con = [];
            if($companyId && static::hasField('company_id')){
                $con[] = ['company_id','=',$companyId];
            }
            // return static::selectX($con);
            // 20230621:OSS图片路径是动态的，原方法有bug
            // 20250216废弃
            // return static::lists($con);
            return static::inst()->reset()->where($con)->select();
        });
        return $res;
    }
    /**
     * 条件查询(list)
     * @useFul 1
     * @param type $con
     * @param type $companyId
     * @return type
     */
    public static function staticConList($con = [],$companyId = '',$sort="",$field=[]){
        $keyMd5     = md5(json_encode($con));
        $dbSource   = static::dbSource();
        $key        = static::staticBaseCacheKey($companyId). $dbSource. $sort.'_List'.$keyMd5;

        static::staticCacheKeysSet($companyId, $key);
        return static::cacheMt()::funcGet( $key, function() use ($con, $companyId, $sort, $field ){
            // 判断数据库量是否过大
            if(!static::$staticListsAll && static::staticIsLarge($companyId)){
                // 20230905增加，若数据量过大，直接查数据库，再缓存
                if($companyId && static::hasField('company_id')){
                    $con[] = ['company_id','=',$companyId];
                }
                $res = static::lists($con, $sort);
            } else {
                // 原来的全量缓存
                $listsAll   = static::staticListsAll($companyId);
                $res        = Arrays2d::listFilter($listsAll, $con);
                if($sort){
                    $sotArr = explode(' ',$sort);
                    $res = Arrays2d::sort($res, $sotArr[0],Arrays::value($sotArr, 1));
                }
                if($field){
                    $res = Arrays2d::getByKeys($res, $field);
                }
            }
            return $res;
            
        });
    }
    /**
     * 数据库量是否过大
     * 以1000条为界限
     * 超过1000条的，查询直接查数据库
     * @useFul 1
     */
    public static function staticIsLarge($companyId){
        return static::staticAllRecordCount($companyId) > 1000;
    }
    
    /**
     * 20230905
     * 数据表全部记录数量
     * 当数据表记录数量过大时，性能
     * @useFul 1
     * @param type $con
     * @param type $companyId
     * @return type
     */
    public static function staticAllRecordCount($companyId = ''){
        if(!$companyId){
            $companyId = session(SESSION_COMPANY_ID);
        }
        $key        = static::staticBaseCacheKey($companyId).'_AllRecordCount';
        static::staticCacheKeysSet($companyId, $key);
        return static::cacheMt()::funcGet( $key, function(){
            $rr = static::conCount();
            return $rr;
        });
    }

    /**
     * 20221109：清除缓存
     * @param type $companyId
     * @return type
     */
    public static function staticCacheClear($companyId = ''){
        $key            = static::staticBaseCacheKey($companyId);
        static::cacheMt()::rm($key); 
        // 20230805：删除一些关联的缓存
        $keyName        = static::staticBaseCacheKey($companyId).'_KEYS';
        $keyNameArr     = static::cacheMt()::get($keyName) ? : [];
        // dump($keyNameArr);
        foreach($keyNameArr as &$v){
            static::cacheMt()::rm($v); 
        }
        static::cacheMt()::rm($keyName); 
    }
        
    /**
     * 20231212:只取id，静态
     * @param type $con
     * @param type $companyId
     * @return type
     */
    public static function staticConIds($con = [],$companyId = ''){
        $arr = static::staticConList($con, $companyId);
        return Arrays2d::uniqueColumn($arr, 'id');
    }
    
    /**
     * 20220619:增加count
     * @param type $con
     * @param type $companyId
     * @return type
     */
    public static function staticConCount($con = [],$companyId = ''){
//        $listsAll = static::staticListsAll($companyId);
//        $res = Arrays2d::listFilter($listsAll, $con);
        // 20230801
        $res = static::staticConList($con, $companyId);
        return count($res);
    }
    /**
     * 20221020带键过滤
     * @param type $con
     * @param type $keys
     * @param type $companyId
     * @return type
     */
    public static function staticConFindKeys($con = [], $keys = [], $companyId = ''){
        $info = static::staticConFind($con, $companyId);
        if($info && is_object($info)){
            $info = $info->toArray();
        }
        return $info ? Arrays::getByKeys($info, $keys) : [];
    }
    /**
     * 取单一列
     * @param type $con
     * @param type $companyId
     */
    public static function staticConColumn($field, $con = [],$companyId = ''){
//        $listsAll = static::staticListsAll($companyId);
//        // 过滤后的数组
//        $listFilter =  Arrays2d::listFilter($listsAll, $con);
        // 20230801
        $listFilter = static::staticConList($con, $companyId);        
        $fields = explode(',', $field);
        if(count($fields) > 1){
            //多个
            return Arrays2d::getByKeys($listFilter, $fields);
        } else {
            //单个
            return array_column($listFilter, $field);
        }
    }
    /**
     * 20230429：静态的分组统计
     */
    public static function staticGroupBatchCount($key, $keyIds, $con = []){
        $con[] = [$key, 'in', $keyIds];
        if (static::inst()->hasField('is_delete')) {
            $con[] = ['is_delete', '=', 0];
        }
        $lists = static::staticConList($con);
        // 20230730：处理其他
        $result = array_fill_keys($keyIds, 0);
        $res    = array_count_values(array_column($lists, $key));
        // 20230730：处理其他
        return Arrays::concat($result, $res);
    }
    
    /**
     * 静态get方法
     * 20250824:优化为单条缓存
     */
    public function staticGet($companyId = ''){
        $cacheKey = __METHOD__.$this->uuid.$companyId;
        // 20250824:优化为键值对提取
        $info = static::cacheMt()::funcGet($cacheKey, function () use($companyId) {
                    $con[] = ['id','=',$this->uuid];
                    $info = static::staticConFind($con, $companyId);
                    return $info;
                });        
        return $info;
    }
}

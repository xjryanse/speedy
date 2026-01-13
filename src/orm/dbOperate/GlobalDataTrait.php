<?php

namespace xjryanse\speedy\orm\dbOperate;

use xjryanse\speedy\facade\DbOrm;
use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\logic\Arrays2d;
use Exception;
/**
 * 全局待提交数据逻辑
 */
trait GlobalDataTrait {
    
    
    private static function glDeleteDataDeal() {
        global $glDeleteData;
        if (!$glDeleteData) {
            return false;
        }
        foreach ($glDeleteData as $tableName => $ids) {
            $con = [];
            $con[] = ['id', 'in', array_unique($ids)];
            DbOrm::table($tableName)->where($con)->delete();
        }
        return true;
    }

    private static function glUpdateDataDeal() {
        global $glUpdateData;
        if (!$glUpdateData) {
            return false;
        }
        foreach ($glUpdateData as $dbSource => $glSaveDataRaw) {
            foreach ($glSaveDataRaw as $tableName => $dataArr) {
                foreach ($dataArr as $id => $data) {
                    $upCon = [['id','=',$id]];
                    DbOrm::table($tableName, $dbSource)->where($upCon)->update($data);
                }
            }
        }
        return true;
    }

    private static function glSqlQueryUDeal() {
        global $glSqlQuery;
        if (!$glSqlQuery) {
            return false;
        }
        foreach ($glSqlQuery as $dbSource=>$sqlArr) {
            $sqlU = array_unique($sqlArr);
            foreach($sqlU as $sql){
                DbOrm::execute($sql, $dbSource);
            }
        }
        return true;
    }
    /**
     * 20250209:全局批量保存数据处理
     * @global type $glSaveData
     * @return bool
     */
    private static function glSaveDataDeal() {
        global $glSaveData;
        if (!$glSaveData) {
            return false;
        }
        //【1】保存的数据
        // 20250412：增加dbSource
        foreach ($glSaveData as $dbSource => $glSaveDataRaw) {
            foreach ($glSaveDataRaw as $tableName => $dataArr) {
                //20220621;解决批量字段不同步bug                
                $saveArr = [];
                foreach ($dataArr as $id => $data) {
                    $keys = array_keys($data);
                    sort($keys);
                    ksort($data);
                    $keyStr = md5(implode(',', $keys));
                    $saveArr[$keyStr][] = $data;
                }
                // 20220621
                foreach ($saveArr as $k => $arr) {
                    $sql = static::saveAllSql($tableName, array_values($arr));
                    // 20250215:source改造
                    DbOrm::sourceInst($dbSource)->execute($sql);
                }
            }
        }
        return true;
    }
        /**
     * 20250214：全局待更数据影响的源库；
     */
    public static function globalEffDbSource(){
        global $glSaveData, $glUpdateData, $glDeleteData, $glSqlQuery;
        $tableS = $glSaveData ? array_keys($glSaveData) : [];
        $tableU = $glUpdateData ? array_keys($glUpdateData) : [];
        $tableD = $glDeleteData ? array_keys($glDeleteData) : [];
        
        $table      = array_merge($tableS, $tableU, $tableD);
        
        $tableArr   = static::allTableArr();
        $con    = [];
        $con[]  = ['table','in',$table];
        $dbSourceArr = array_unique(array_column(Arrays2d::listFilter($tableArr, $con), 'dbSource'));
        return $dbSourceArr;
    }
    
    /**
     * 是否在全局删除中
     */
    public static function isGlobalDelete($tableName, $id) {
        $globalDelIds = static::tableGlobalDeleteIds($tableName);
        return in_array($id, $globalDelIds);
    }

    /**
     * 是否在全局添加中
     */
    public static function isGlobalSave($tableName, $id) {
        global $glSaveData;
        $saveDatas = Arrays::value($glSaveData, $tableName, []);
        return in_array($id, array_column($saveDatas, 'id'));
    }
    
    /**
     * 应在控制器层最外循环结尾调用，并加事务
     * 如何解决锁的问题？？
     */
    public static function dealGlobal() {
        global $glSaveData, $glUpdateData, $glDeleteData, $glSqlQuery;
        // 20250214
        $dbSourceArr = static::globalEffDbSource();
        DbOrm::startTrans($dbSourceArr);
        //【3】删除的数据
        static::glDeleteDataDeal();
        // 保存数据处理
        static::glSaveDataDeal();
        //【2】更新的数据
        static::glUpdateDataDeal();
        //【4】执行自定义sql
        static::glSqlQueryUDeal();
        DbOrm::commit($dbSourceArr);
        // 20231116:执行完毕后清空
        $glSaveData = [];
        $glUpdateData = [];
        $glDeleteData = [];
        $glSqlQuery = [];
        return true;
    }
    
    /**
     * 增加一条全局执行sql
     * @global array $glSqlQuery
     * @param type $sql
     * @return bool
     */
    public static function pushGlobalSql($sql) {
        global $glSqlQuery;
        //扔一条sql到全局变量，方法执行结束后执行
        $glSqlQuery[] = $sql;
        return true;
    }
    /**
     * 传表名获取全局新增数据
     */
    public static function tableGlobalSaveData($tableName, $dbSource){
        global $glSaveData;
        // 库数据
        $sourceData = Arrays::value($glSaveData, $dbSource) ? : [];
        // 表数据
        $tableData  = Arrays::value($sourceData, $tableName) ? : [];
        return $tableData;
    }
    /**
     * 全局更新数据
     * @global type $glUpdateData
     * @param type $tableName
     * @param type $dbSource
     * @return type
     */
    public static function tableGlobalUpdateData($tableName, $dbSource){
        global $glUpdateData;
        // 库数据
        $sourceData = Arrays::value($glUpdateData, $dbSource) ? : [];
        // 表数据
        $tableData  = Arrays::value($sourceData, $tableName) ? : [];
        return $tableData;
    }
    /**
     * 20230914:提取指定数据表处于全局删除中的id
     * @param type $tableName
     */
    public static function tableGlobalDeleteIds($tableName, $dbSource='dbBusi') {
        global $glDeleteData;
//        dump($glDeleteData);
//        if($glDeleteData){
//            throw new Exception('待调测');
//        }
//        
        return Arrays::value($glDeleteData, $tableName, []);
    }
    /**
     * 用于调试内存数据
     */
    public static function dumpGlobalData(){
        global $glSaveData, $glUpdateData, $glDeleteData, $glSqlQuery;
        
        dump('$glSaveData');
        dump($glSaveData);
        dump('$glUpdateData');
        dump($glUpdateData);
        dump('$glDeleteData');
        dump($glDeleteData);
        dump('$glSqlQuery');
        dump($glSqlQuery);

    }
    
}

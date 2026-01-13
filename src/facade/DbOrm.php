<?php

namespace xjryanse\speedy\facade;

use xjryanse\speedy\Facade;
use xjryanse\speedy\core\DbOrm as DbOrmSource;
use xjryanse\speedy\orm\DbOperate;
/**
 * @see \speedy\core\DbOrm
 * 有限多例类的门面
 */
class DbOrm extends Facade{
    /**
     * 开启事务
     * @param type $dbSourceArr
     */
    public static function startTrans($dbSourceArr = []){
        foreach($dbSourceArr as $s){
            DbOrmSource::inst($s)->startTrans();
        }
    }
    /**
     * 提交事务
     * @param type $dbSourceArr
     */
    public static function commit($dbSourceArr = []){
        foreach($dbSourceArr as $s){
            DbOrmSource::inst($s)->commit();
        }
    }
    /**
     * 20250214:封装
     * @param type $table
     * @param type $source  指定数据库
     * @return type
     */
    public static function table($table, $source = ''){
        // 一般的查询不需要传source参数，由table取，自定义sql需传
        if(!$source){
            $source = DbOperate::tableNameDbSource($table);
        }
        return DbOrmSource::inst($source)->table($table)->instInit();
    }
    
    public static function query($sql, $source){
        // $source = DbOperate::tableNameDbSource($table);
        return DbOrmSource::inst($source)->query($sql);
    }
    
    public static function execute($sql, $source){
        // $source = DbOperate::tableNameDbSource($table);
        return DbOrmSource::inst($source)->execute($sql);
    }
    /**
     * 20250215:源实例
     */
    public static function sourceInst($source){
        return DbOrmSource::inst($source);
    }
    
    /**
     * 开启事务
     * @param type $dbSourceArr
     */
    public static function rollback($dbSourceArr = []){
        foreach($dbSourceArr as $s){
            DbOrmSource::inst($s)->rollback();
        }
    }
    
}

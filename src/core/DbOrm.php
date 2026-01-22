<?php

namespace xjryanse\speedy\core;

use xjryanse\speedy\core\Orm;
use xjryanse\speedy\core\Db;
use Exception;
/**
 * 先调用Orm封装sql语句，再调用Db类库执行sql语句
 */
class DbOrm {
    // $uuid为数据库；每个数据库一个实例    
    use \xjryanse\speedy\traits\InstMultiTrait;
    //【单例】
    // use \xjryanse\speedy\traits\InstTrait;

    private $db;
    private $orm;
    
    /**
     * 
     * @param type $dbSource    20250213:默认业务库
     */
    protected function __construct($dbSource = 'dbBusi') {
        $this->uuid     = $dbSource;
        // 20250216
        if(!$dbSource){
            throw new Exception('dbSource必须');
        }
        // todo:慢慢过渡只用dbId
        if(is_numeric($dbSource)){
            $dbId = $dbSource;
            $this->db   = Db::inst($dbId);
        } else {
            $this->db   = Db::inst($dbSource);
        }
        
        $this->orm  = Orm::inst();
    }
    
    /**
     * 初始化；
     */
    public function instInit(){
        $this->db->instInit();
        $this->orm->instInit();
        return $this;
    }

    public function table($table) {
        $this->orm->setTable($table);
        return $this;
    }
    public function alias($alias) {
        $this->orm->alias($alias);
        return $this;
    }
    
    public function join($joinTable, $joinCondition) {
        $this->orm->join($joinTable, $joinCondition);
        return $this;
    }
    
    public function leftJoin($joinTable, $joinCondition) {
        $this->orm->leftJoin($joinTable, $joinCondition);
        return $this;
    }
    
    public function rightJoin($joinTable, $joinCondition) {
        $this->orm->rightJoin($joinTable, $joinCondition);
        return $this;
    }

    public function where($con) {
        $this->orm->where($con);
        return $this;
    }
    
    public function whereOr($con) {
        $this->orm->whereOr($con);
        return $this;
    }
    
    public function whereRaw($conStr) {
        $this->orm->whereRaw($conStr);
        return $this;
    }
    
    public function having($conStr) {
        $this->orm->having($conStr);
        return $this;
    }

    public function order($order) {
        $this->orm->order($order);
        return $this;
    }
    
    public function group($order) {
        $this->orm->group($order);
        return $this;
    }
    
    public function field($field) {
        $this->orm->field($field);
        return $this;
    }

    public function limit($perPage, $start = 0) {
        $this->orm->limit($perPage, $start);
        return $this;
    }

    public function cache($cacheTime = 3600) {
        $this->db->cache($cacheTime);
        return $this;
    }

    public function select() {
        $sql = $this->orm->select();
        return $this->db->query($sql);
    }
    
    public function buildSql() {
        $sql = $this->orm->select();
        return $sql;
    }
    
    /**
     * 20250213
     * @param type $sql
     * @return type
     */
    public function query($sql, array $bind = []) {
        return $this->db->query($sql, $bind);
    }
    
    public function execute($sql) {
        return $this->db->execute($sql);
    }

    public function find() {
        $this->limit(1);
        $result = $this->select();
        return !empty($result) ? $result[0] : null;
    }
    
    public function column($valueField, $tableKey='') {
        $sql = $this->orm->select();
        $lists = $this->db->query($sql);

        return $lists ? array_column($lists, $valueField, $tableKey) : [];
    }

    public function count($field = '1') {
        $sql = $this->orm->count($field);
        $result = $this->db->query($sql);
        return $result[0]['count'] ?? 0;
    }
    
    public function paginate($page = 1, $perPage = 10) {
        // 计算偏移量
        $offset = ($page - 1) * $perPage;
        // 设置分页查询的 limit 条件
        $this->limit($perPage, $offset);
        // 获取当前页的数据
        $data = $this->select();
        // 获取总记录数
        $total = $this->count();
        // 计算总页数
        $totalPages = ceil($total / $perPage);
        // 返回分页信息和数据
        return [
            'data'      => $data,
            'total'     => $total,
            'per_page'  => $perPage,
            'current_page' => $page,
            'last_page' => $totalPages,
        ];
    }
    
/*****以下是写库操作*************************/
    public function update(array $data, array $con = []) {
        $sql = $this->orm->update($data, $con);
        return $this->db->execute($sql);
    }

    public function delete() {
        $sql = $this->orm->delete();
        return $this->db->execute($sql);
    }
    public function insertBatch(array $data) {
        $sql = $this->orm->insertBatch($data);
        return $this->db->execute($sql);
    }

    public function insert(array $data) {
        return $this->insertBatch([$data]);
    }

    public function startTrans() {
        return $this->db->startTrans();
    }

    public function commit() {
        return $this->db->commit();
    }
    
    public function rollback() {
        return $this->db->rollback();
    }
}

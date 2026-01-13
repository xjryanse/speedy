<?php

namespace xjryanse\speedy\core;

use xjryanse\speedy\logic\ModelQueryCon;
use xjryanse\speedy\logic\Arrays2d;
use Exception;
/**
 * 对象关系映射
 */
class Orm {
    //【单例】
    // use \xjryanse\speedy\traits\InstTrait;
    // $uuid为table；每个table一个实例
    use \xjryanse\speedy\traits\InstMultiTrait;
    // 数据表
    protected $table;
    protected $alias;
    // 查询参数：
    private $conArr     = [];
    private $conRaw     = '';
    private $orderBy    = '';
    private $groupBy    = '';
    private $fieldArr   = [];
    private $joins      = [];
    private $limit      = '';
    private $having     = '';
    /**
     * 20250209:每张表一个实例
     * @param type $uuid
     */
    public function __construct($uuid = 0) {
        $this->setTable($uuid);
    }

    /**
     * 20250214初始化；
     */
    public function instInit(){
        $this->conArr   = [];
        $this->conRaw   = '';
        $this->orderBy  = '';
        $this->groupBy  = '';
        $this->fieldArr = [];
        $this->joins    = [];
        $this->limit    = '';
        $this->alias    = '';
        return $this;
    }
    
    public function setTable($table) {
        $this->table = $table;
    }
    // 设置表别名
    public function alias($alias) {
        $this->alias = $alias;
        return $this;
    }
    
    public function join($joinTable, $joinCondition, $joinType = 'JOIN' ) {
        $validJoinTypes = ['JOIN', 'LEFT JOIN', 'RIGHT JOIN'];
        if (!in_array(strtoupper($joinType), $validJoinTypes)) {
            throw new Exception('不支持的连接类型: ' . $joinType);
        }
        $this->joins[] = [
            'type' => strtoupper($joinType),
            'table' => $joinTable,
            'condition' => $joinCondition
        ];
        return $this;
    }
    
    public function leftJoin($joinTable, $joinCondition){
        return $this->join($joinTable, $joinCondition, 'LEFT JOIN');
    }

    public function rightJoin($joinTable, $joinCondition){
        return $this->join($joinTable, $joinCondition, 'RIGHT JOIN');
    }

    // where 方法，用于设置查询条件;返回this支持链式调用
    public function where($con, $orIndex = 1) {
        if(!$con){
            return $this;
        }
        if(!is_array($con)){
            throw new Exception('不支持的查询条件');
        }
        $this->conArr[$orIndex] = isset($this->conArr[$orIndex]) 
                ? Arrays2d::unique(array_merge($this->conArr[$orIndex],$con)) : $con;
        return $this;
    }
    // where 方法，用于设置查询条件;返回this支持链式调用
    public function whereOr($con) {
        if(!is_array($con)){
            throw new Exception('不支持的或查询条件');
        }
        $orIndex = $this->conArr ? max(array_keys($this->conArr)) + 1 : 1;
        $this->conArr[$orIndex] = $con;
        return $this;
    }
    
    public function whereRaw($conStr) {
        if(!is_string($conStr)){
            throw new Exception('不支持的原生查询条件');
        }
        $this->conRaw = $conStr;
        return $this;
    }
    
    /**
     * 新增 having 方法，用于设置 HAVING 子句的条件
     */
    public function having($condition) {
        if (!is_string($condition)) {
            throw new Exception('不支持的 HAVING 条件');
        }
        $this->having = $condition;
        return $this;
    }
    
    public function order($order) {
        $this->orderBy = $order;
        return $this;
    }
    // 新增 group 方法，用于设置分组条件
    public function group($group) {
        $this->groupBy = $group;
        $this->field($group);
        return $this;
    }
    // field 方法，用于设置查询的字段
    public function field($field) {
        if(is_string($field)){
            $field = explode(',',$field);
        }
        $this->fieldArr = array_unique(array_merge($this->fieldArr,$field));
        return $this;
    }

    // limit 方法，用于设置查询的偏移量和数量
    public function limit($perPage, $start=0) {
        if($perPage){
            $this->limit = "$start, $perPage";
        }
        return $this;
    }

    private function whereStrGenerate(){
        if (!$this->conArr) {
            return '';
        }
        $whereOrStrArr = [];
        foreach($this->conArr as $con){
            $whereStr           = ModelQueryCon::conditionParse($con);
            $whereOrStrArr[]    = '(' . $whereStr.')';
        }
        $whereRaw = implode(' or ', $whereOrStrArr);
        if($this->conRaw){
            $whereRaw .= $whereRaw ? ' and ' :'';
            $whereRaw .= $this->conRaw;
        }
        
        return $whereRaw;
    }
    
    private function joinStrGenerate() {
        if (empty($this->joins)) {
            return '';
        }
        $joinStrs = [];
        foreach ($this->joins as $join) {
            $joinStrs[] = "{$join['type']} {$join['table']} ON {$join['condition']}";
        }
        return implode(' ', $joinStrs);
    }

    /**
     * 20250203
     */
    public function select() {
        foreach($this->fieldArr as $k=>$v){
            if($v == '*'){
                unset($this->fieldArr[$k]);
            }
        }
        $fieldStr = $this->fieldArr ? implode(',',$this->fieldArr) : '*';

        $tableStr = $this->table;
        if ($tableStr && $this->alias) {
            $tableStr .= ' '.$this->alias;
        }
        
        $sql = "SELECT ".$fieldStr;
        if($tableStr){
            $sql .= " FROM " . $tableStr;
        }
        $joinStr = $this->joinStrGenerate();
        if ($joinStr) {
            $sql .= " " . $joinStr;
        }
        
        $whereStr = $this->whereStrGenerate();
        if ($whereStr) {
            $sql .= " where " . $whereStr;
        }
        if ($this->groupBy) {
            $sql .= " GROUP BY " . $this->groupBy;
        }
        if ($this->having) {
            $sql .= " HAVING " . $this->having;
        }
        
        if ($this->orderBy) {
            $sql .= " order by " . $this->orderBy;
        }
        if ($this->limit) {
            $sql .= " limit " . $this->limit;
        }

        return $sql;
    }

    public function update(array $data) {
        if (empty($this->table)) {
            throw new \Exception('orm映射表未设置');
        }

        if (empty($data)) {
            throw new \Exception('更新数据不可为空');
        }
        if (!$this->conArr) {
            throw new \Exception('更新条件必须，请先使用 where 方法设置条件');
        }

        $setStr = '';
        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $value = "'" . addslashes($value) . "'";
            } elseif (is_null($value)) {
                $value = 'NULL';
            }
            if ($setStr) {
                $setStr .= ', ';
            }
            $setStr .= '`'.$field . '` = ' . $value;
        }

        $whereStr = $this->whereStrGenerate();

        $sql = "UPDATE " . $this->table . " SET " . $setStr;
        $sql .= " WHERE " . $whereStr;

        return $sql;
    }
    
    public function updateRaw($data){
        if (empty($this->table)) {
            throw new \Exception('orm映射表未设置');
        }

        if (empty($data)) {
            throw new \Exception('更新数据不可为空');
        }
        if (!$this->conArr) {
            throw new \Exception('更新条件必须，请先使用 where 方法设置条件');
        }

        $setStr = '';
        foreach ($data as $field => $value) {
//            if (is_string($value)) {
//                $value = "'" . addslashes($value) . "'";
//            } elseif (is_null($value)) {
//                $value = 'NULL';
//            }
            if ($setStr) {
                $setStr .= ', ';
            }
            $setStr .= '`'.$field . '` = ' . $value;
        }

        $whereStr = $this->whereStrGenerate();

        $sql = "UPDATE " . $this->table . " SET " . $setStr;
        $sql .= " WHERE " . $whereStr;

        return $sql;
    }
    
    public function count($field = '1') {
        if (empty($this->table)) {
            throw new \Exception('orm映射表未设置');
        }

        $sql = "SELECT COUNT({$field}) as count FROM " . $this->table;
        $whereStr = $this->whereStrGenerate();
        if ($whereStr) {
            $sql .= " where " . $whereStr;
        }

        return $sql;
    }
    
    public function delete() {
        if (empty($this->table)) {
            throw new \Exception('orm映射表未设置');
        }
        if (empty($this->conArr)) {
            throw new \Exception('删除条件必须，请先使用 where 方法设置条件');
        }
        $whereStr = $this->whereStrGenerate();

        $sql = "DELETE FROM " . $this->table . " WHERE " . $whereStr;
        return $sql;
    }
    
    /**
     * 批量插入数据
     * @param array $data 要插入的数据，二维关联数组，每个子数组为一条记录，键为字段名，值为字段值
     * @return string 构建好的插入 SQL 语句
     * @throws \Exception 如果未设置表名或插入数据为空
     */
    public function insertBatch(array $data) {
        if (empty($this->table)) {
            throw new \Exception('orm映射表未设置');
        }

        if (empty($data)) {
            throw new \Exception('插入数据不可为空');
        }

        $fields = array_keys($data[0]);
        $escapedFields = array_map(function ($field) {
            return "`$field`";
        }, $fields);
        $fieldStr = implode(', ', $escapedFields);

        $valueSets = [];
        foreach ($data as $record) {
            $values = [];
            foreach ($fields as $field) {
                $value = $record[$field] ?? null;
                if (is_string($value)) {
                    $values[] = "'" . addslashes($value) . "'";
                } elseif (is_null($value)) {
                    $values[] = 'NULL';
                } else {
                    $values[] = $value;
                }
            }
            $valueSets[] = '(' . implode(', ', $values) . ')';
        }

        $valueSetStr = implode(', ', $valueSets);

        $sql = "INSERT INTO " . $this->table . " (" . $fieldStr . ") VALUES " . $valueSetStr;

        return $sql;
    }

    /**
     * 单条插入数据，调用批量插入方法
     * @param array $data 要插入的数据，关联数组，键为字段名，值为字段值
     * @return string 构建好的插入 SQL 语句
     */
    public function insert(array $data) {
        return $this->insertBatch([$data]);
    }

    
    
    
    
    
    
    
    
    
    
    
    
}

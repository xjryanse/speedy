<?php

namespace xjryanse\speedy\orm\baseTraits;

use xjryanse\speedy\orm\DbOperate;
use xjryanse\speedy\core\Db;
use xjryanse\speedy\logic\SnowFlake;
use xjryanse\speedy\facade\Request;
use Exception;
/**
 * 数据和模型映射复用
 * 20230805
 */
trait DbOrmTrait {
    
    private $con        = [];
    private $conRaw     = '';
    private $orderBy    = '';
    private $groupBy    = '';
    private $fieldArr   = [];
    private $limit      = [];
    
    // 缓存时间（秒）
    private $cacheTime  = 0;
    
    protected function dbOrmInstInit(){
        $this->con      = [];
        $this->orderBy  = '';
        $this->groupBy  = '';
        $this->fieldArr = [];
        $this->limit    = [];
    }
    /**
     * 20250912
     * @return type
     */
    protected function getThisTable() {
        return $this->table;
    }
    
    /**
     * 数据库表
     * @return type
     */
    protected static function getTable() {
        // 数据库表前缀
        $prefix         = 'w_';
        $className      = static::class;
        $shortClassName = substr($className, strrpos($className, '\\') + 1);
        $tableNameN     = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortClassName));
        return $prefix.$tableNameN;
    }
    /**
     * 实际操作的表；有分表的取分表
     */
    protected static function getOperateTable($uuid = ''){
        if($uuid && method_exists(static::class, 'sepIdTableSet')){
            // 20250718：按年分表的处理
            return static::sepIdTableSet($uuid);
        } else {
            return static::getTable();
        }
    }

    /**
     * 20250217:当前类表是否存在
     */
    public static function hasTable(){
        $table = static::getTable();
        return DbOperate::isTableExist($table);
    }
    
    /**
     * 
     * @return type
     */
    public static function dbSource(){
        return property_exists(static::class, 'dbSource') ? static::$dbSource : 'dbBusi';
    }
    /**
     * 20250328
     * @param type $dbSource
     * @throws Exception
     */
    public static function setDbSource($dbSource){
        if(!property_exists(static::class, 'dbSource')){
            throw new Exception(static::class.'未设置属性dbSource,请联系开发');
        }
        static::$dbSource = $dbSource;
    }
    
    public static function dbInst(){
        return Db::inst(static::dbSource());
    }

    /**
     * 20250204：字段存在
     * @param type $fieldName
     * @return type
     */
    public static function hasField($fieldName){
        // static::queryCountCheck(__METHOD__);
        return DbOperate::hasField(static::getTable(), $fieldName);
    }
    
    
    // where 方法，用于设置查询条件;返回this支持链式调用
    /**
     * 
     * @param type $con
     * @param array $sepKeys    分表keys
     * @return $this
     */
    public function where($con = [], $sepKeys = []) {
        // 20231020：增加分表逻辑（体检板块）
        if (property_exists(static::class, 'isSeprate') && static::$isSeprate) {
            static::inst()->setConTable($con, $sepKeys);
        }
        $this->con = $con;
        return $this;
    }
    // 20251118
    public function whereRaw($conRaw){
        $this->conRaw     = $conRaw;
        return $this;
    }

    public function order($order) {
        $this->orderBy = $order;
        return $this;
    }

    // field 方法，用于设置查询的字段
    public function field($field) {
        if(is_string($field)){
            $field = explode(',',$field);
        }
        if(is_int($field)){
            throw new Exception('$field不能是数字');
        }
        $this->fieldArr = array_unique(array_merge($this->fieldArr,$field));
        return $this;
    }

    // limit 方法，用于设置查询的偏移量和数量
    public function limit($perPage, $start=0) {
        $this->limit = [$start, $perPage];
        return $this;
    }
    // 新增 group 方法，用于设置分组条件
    public function group($group) {
        $this->groupBy = $group;
        $this->field($group);
        return $this;
    }
    
    public function cache($cacheTime = 3600) {
        $this->cacheTime = $cacheTime;
        return $this; // 返回 $this 以支持链式调用
    }
    /**
     * 取单条
     * @param array $con
     * @return type
     */
    public function find(){
        $this->orm->setTable($this->table);        
        // 组装查询sql
        $inst = $this->orm->where($this->con);
        if($this->orderBy){
            $inst->order($this->orderBy);
        }
        if($this->fieldArr){
            $inst->field($this->fieldArr);
        }
        $sql    = $inst->limit(1)->select();
        // 数据库操作类执行
        $item    = static::dbInst()->cache($this->cacheTime)->find($sql);
        if ($item && $this->fieldArr == '*') {
            static::inst($item['id'])->setUuData($item);
        }
        return $item;
    }
    
    /**
     * 取列表
     * @param array $con
     * @return type
     */
    public function select(){
        // 20250718
        $this->orm->setTable($this->table);
        // 组装查询sql
        $inst = $this->orm->where($this->con);
        if($this->conRaw){
            $inst->whereRaw($this->conRaw);
        }
        if($this->orderBy){
            $inst->order($this->orderBy);
        }
        if($this->groupBy){
            $inst->group($this->groupBy);
        }
        if($this->fieldArr){
            $inst->field($this->fieldArr);
        }
        if($this->limit){
            $inst->limit($this->limit[1], $this->limit[0]);
        }
        $sql    = $inst->select();
        // 数据库操作类执行
        $res    = static::dbInst()->cache($this->cacheTime)->query($sql);
        return $res;
    }
    
    public function column($valueField, $tableKey=null) {
        $fieldArr = $valueField ? [$valueField] : [];
        if($tableKey){
            $fieldArr[] = $tableKey;
        }

        $this->field($fieldArr);
        $lists = $this->select();

        return $lists ? array_column($lists, $valueField, $tableKey) : [];
    }
    
    /**
     * 20250204
     * @param array $con
     * @return type
     */
    public function count(){
        $sql = $this->orm->where($this->con)->count();
        // 数据库操作类执行
        $res    = static::dbInst()->find($sql);
        return $res ? $res['count'] : 0;
    }
    
    
    /*******************************************************/
    /**
     * 20250215：控制更新操作统一走ram方法
     * @param type $data
     * @return type
     */
    protected function update($data){
        $con    = [];
        $con[]  = ['id','=',$this->uuid];
        // 组装查询sql
        $sql    = $this->orm->where($con)->update($data);
        // 数据库操作类执行
        $res    = static::dbInst()->execute($sql);
        return $res;
    }
    /**
     * 20251109
     * @param type $data
     * @return type
     */
    protected function updateRaw($data){
        $con    = [];
        $con[]  = ['id','=',$this->uuid];
        // 组装查询sql
        $sql    = $this->orm->where($con)->updateRaw($data);
        // 数据库操作类执行
        $res    = static::dbInst()->execute($sql);
        return $res;
    }    
    
    /**
     * 20250215：控制写入操作统一走ram方法
     * @param type $data
     * @return type
     */
    protected function save($data) {
        //Orm::setTable(static::getTable());
        if(!isset($data['id'])){
            $data['id'] = SnowFlake::generateParticle();
        }
        // 组装查询sql
        $sql    = $this->orm->insert($data);
        // 数据库操作类执行
        $res    = static::dbInst()->execute($sql);
        return $res;
    }

    public static function getLastSql(){
        return 'test';
    }
    /**
     * 20250215:判断是否在事务中
     * @return type
     */
    public static function inTransaction() {
        return static::dbInst()->inTransaction();
    }
    
}

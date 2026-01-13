<?php

namespace xjryanse\speedy\orm\baseTraits;

use xjryanse\speedy\orm\DbOperate;
use orm\system\SystemColumn;
use orm\system\SystemColumnList;

use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\logic\Arrays2d;
use xjryanse\speedy\logic\Strings;
use xjryanse\speedy\logic\Debug;
use xjryanse\speedy\logic\SnowFlake;
use Exception;

/**
 * 提供一个集中的方法，管理内存中的待提交数据
 */
trait MainModelRamTrait {
    
    public static function newId() {
        $newId = SnowFlake::generateParticle();
        return strval($newId);
    }
    /**
     * 保存时的图片处理，支持单图多图
     * @param type $value
     * @return type
     */
    public static function setImgVal($value) {
        // || is_object($value)
        if ($value && is_array($value)) {
            //isset( $value['id']):单图；否则：多图
            //对象转成数组，比较好处理
            $value = isset($value['id']) ? $value['id'] : implode(',', array_column($value, 'id'));
        }
        return $value;
    }

    /**
     * 20220619
     * @global array $glDeleteData
     * @return type
     */
    public function deleteRam() {
        static::queryCountCheck(__METHOD__);           
        $rawData = $this->get();
        //删除前
        if (method_exists(static::class, 'ramPreDelete')) {
            $this->ramPreDelete();      //注：id在preSaveData方法中生成
        }
        // 20230912:谨慎测试
        // $tableName = static::getTable();
        $tableName = static::getRawTable();
        DbOperate::checkCanDelete($tableName, $this->uuid);

        $this->doDeleteRam();
        // 20230519:更新
        static::delObjAttrs($rawData, $this->uuid);
        //删除后
        if (method_exists(static::class, 'ramAfterDelete')) {
            $this->ramAfterDelete($rawData);
        }
        //20230729
        static::dataCacheClear();
        
        return $this->uuid;
    }
        
    /**
     * 20220703;?仅执行删除动作
     * @global type $glSaveData
     * @global array $glDeleteData
     * @return boolean
     */
    public function doDeleteRam(){
        global $glSaveData,$glDeleteData;
        // $tableName = static::getTable();
        // 20250721
        $tableName = static::getOperateTable($this->uuid);
        //20220625:还未写入数据库的，直接在内存中删了就行
        if(isset($glSaveData[$tableName]) && isset($glSaveData[$tableName][$this->uuid])){
            unset($glSaveData[$tableName][$this->uuid]);
        } else {
            $glDeleteData[$tableName][] = $this->uuid;
        }
        // 20251023:
        $this->uuData = null;
        return true;
    }
    
    /**
     * 2023-02-25:复制数据
     */
    public function copy(){
        /*20250207*/
        $dbSource = static::dbSource();
        $realFieldsArr = DbOperate::realFieldsArr( static::getTable(), $dbSource );
        $resRaw   = static::inst($this->uuid)->get();
        $res = Arrays::getByKeys($resRaw, $realFieldsArr);
        if(!$res){
            throw new Exception('数据不存在'.$this->uuid);
        }
        //20230225:唯一字段复制加copy
        $columnId       = SystemColumn::tableNameGetId(static::getTable());
        $uniqueFields   = SystemColumnList::uniqueFields($columnId);
        foreach($uniqueFields as $v){
            if( isset($res[$v])){ 
                $res[$v] = $res[$v] . 'Copy';
            }
        }

        if( isset($res['id'])){ unset($res['id']);}
        if( isset($res['create_time'])){ unset($res['create_time']);}
        if( isset($res['update_time'])){ unset($res['update_time']);}

        //保存
        $resp   = static::saveRam( $res );
        return $resp;
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    /**************************************************/
    
    
    
    
    
    /**
     * 20240601:全局保存data
     */
    protected static function ramList(){
        global $glSaveData;
        $tableName = static::getRawTable();
        $arr = $glSaveData && $tableName 
                ? Arrays::value($glSaveData, $tableName, []) 
                : [];
        return array_values($arr);
    }
    
    /**
     * 
     */
    public static function ramFind($con) {
        $arr = static::ramList();
        return Arrays2d::listFind($arr, $con);
    }
    
    /**
     * 从列表中，提取一个值，例如id
     * 20240601
     * @param type $field
     */
    protected static function ramValue($field, $con = []){
        $info = static::ramFind($con);
        return Arrays::value($info, $field);
    }

    /**
     * 20220621优化性能
     * @param array $data
     * @param type $preData
     * @return type
     */
    public static function saveAllRam(array &$data, $preData = []) {
        // 20230923:批量保存前处理
        if (method_exists(static::class, 'ramPreSaveAll')) {
            //注：id在preSaveData方法中生成
            static::ramPreSaveAll($data);
        }
        

        foreach ($data as &$v) {
            $tmpData = array_merge($preData, $v);
            // static::saveRam($tmpData);
            // 20240927:优化
            static::commSaveGetIdRam($tmpData);
        }
        return true;
    }
    
    /**
     * 优化性能
     */
    protected static function commSaveGetIdRam($data){
        $mainId = '';
        if (isset($data['id']) && static::inst($data['id'])->get()) {
            $mainId = $data['id'];
            //更新
            $res = static::inst($data['id'])->updateRam($data);
        } else {
            //新增
            $res = static::saveRam($data);
            $mainId = $res['id'];
        }

        return $mainId;
    }
    
    public static function saveGetIdRam($data) {
        return static::commSaveGetIdRam($data);
    }
    
    
    /*
     * 20220619 只保存到内存中
     */
    public static function saveRam($data) {
        // 校验数据表存在
        if(!static::hasTable()){
            $table = static::getTable();
            throw new Exception('数据表'.$table.'不存在');
        }
        static::queryCountCheck(__METHOD__);
        // 20240319:在此写入一些参数，is_delete等，供筛选条件查询
        static::preSaveData($data);
        // 20250719：增加分表判断
        // 20230730：增，在ramPreSave中， updateAuditStatusRam 有循环调用(报销)；
        static::inst($data['id'])->setUuData($data, true);
        if (method_exists(static::class, 'ramPreSave')) {
            static::ramPreSave($data, $data['id']);      //注：id在preSaveData方法中生成
        }
        // 内部进行图片类文件的转化
        static::doSaveRam($data);

        //更新完后执行：类似触发器
        if (method_exists(static::class, 'ramAfterSave')) {
            static::ramAfterSave($data, $data['id']);
        }
        //20230729
        static::dataCacheClear();

        return $data;
    }
    
    public static function doSaveRam($data){
        // 20240422 
        if (!isset($data['id']) || !$data['id']) {
            $data['id'] = static::inst()->newId();
        }
        
        global $glSaveData;
        // 原，再次写入更新
        static::inst($data['id'])->setUuData($data, true);
        //20220619 新增核心
        // 20250719：改增加分表
        $tableName = static::getOperateTable($data['id']);

        $columns = DbOperate::columns($tableName);
        foreach($columns as $column){
            // 20250311：新增的图片处理
            $fieldName = $column['Field'];
            if(property_exists(static::class, 'multiPicFields') && in_array($fieldName, static::$multiPicFields) ){
                $data[$fieldName] = self::setImgVal($data[$fieldName]);
            }
            if(property_exists(static::class, 'picFields') && in_array($fieldName, static::$picFields) ){
                $data[$fieldName] = self::setImgVal($data[$fieldName]);
            }
            // 20250311：原来的获取器逻辑，暂时保留
            $setAttrKey = 'set'.ucfirst(Strings::camelize($fieldName)).'Attr';
            if(isset($data[$fieldName]) && method_exists(static::class, $setAttrKey)){
                $data[$fieldName] = static::inst()->$setAttrKey($data[$fieldName]);
            }
        }
        
        // 20240503
        if (method_exists(static::class, 'savePreCheck')) {
            static::savePreCheck($data);      //注：id在preSaveData方法中生成
        }
        // 20250412
        $dbSource = static::dbSource();
        $glSaveData[$dbSource][$tableName][$data['id']] = $data;
        // Debug::dump('这里');
        // 20230519
        // 20240306 发现体检模块会卡??
        static::pushObjAttrs($data);
        return $data;
    }
    
    /**
     * 20220619
     * @global array $glUpdateData
     * @param array $data
     * @return type
     * @throws Exception
     */
    public function updateRam(array $data) {
        // 20230819：计算更新数组:一般仅用于ramPreUpdate和ramAfterUpdate方法调用
        $this->calUpdateDiffs($data);

        static::queryCountCheck(__METHOD__);        
        $tableName = static::getTable();
        $info = $this->get(0);
        if (!$info) {
            throw new Exception('记录不存在' . $tableName . '表' . $this->uuid);
        }
        if (isset($info['is_lock']) && $info['is_lock']) {
            throw new Exception('记录已锁定不可修改' . $tableName . '表' . $this->uuid);
        }
        //20220624:剔除id，防止误更新。
        if(isset($data['id'])){
            unset($data['id']);
        }
        $data['updater'] = session(SESSION_USER_ID);
        $data['update_time'] = date('Y-m-d H:i:s');
        //额外添加详情信息：固定为extraDetail方法；更新前执行
        if (method_exists(static::class, 'ramPreUpdate')) {
            static::ramPreUpdate($data, $this->uuid);
        }
        //20220620:封装
        $dataSave = $this->doUpdateRam($data);
        // 20231107
        $infoArr = is_object($info) ? $info->toArray() : $info;
        $objAttrData = array_merge($infoArr,$data);
        // 20230519:更新
        // 有bug:如果车票被清理了怎么处理？
        static::updateObjAttrs($objAttrData, $this->uuid);

        //更新完后执行：类似触发器
        if (method_exists(static::class, 'ramAfterUpdate')) {
            static::ramAfterUpdate($data, $this->uuid);
        }
        // 20250524：记录修改日志
        if (method_exists(static::class, 'recordChangeLog')) {
            $this->recordChangeLog();
        }
        //20230729
        static::dataCacheClear();

        return $dataSave;
    }
    
    /**
     * 不关联执行前后触发的更新
     */
    public function doUpdateRam($data){
        // 20240503
        // 20240507:财务反馈无法销账注释
        // static::queryCountCheck(__METHOD__, 2000);
        global $glUpdateData,$glSaveData;
        // 20250719：改增加分表
        $tableName = static::getOperateTable($this->uuid);

        // 设定内存中的值
        $this->setUuData($data);
        $columns = DbOperate::columns($tableName);
        foreach($columns as $column){
            // 20250531：新增的图片处理
            $fieldName = $column['Field'];
            if(property_exists(static::class, 'multiPicFields') && in_array($fieldName, static::$multiPicFields) ){
                $data[$fieldName] = self::setImgVal($data[$fieldName]);
            }
            if(property_exists(static::class, 'picFields') && in_array($fieldName, static::$picFields) ){
                $data[$fieldName] = self::setImgVal($data[$fieldName]);
            }
            
            $setAttrKey = 'set'.ucfirst(Strings::camelize($fieldName)).'Attr';
            //Debug::debug($tableName.'的$setAttrKey之'.$fieldName,$setAttrKey);
            if(isset($data[$fieldName]) && method_exists(static::class, $setAttrKey)){
                $data[$fieldName] = static::inst()->$setAttrKey($data[$fieldName]);
                //dump('获取器'.$setAttrKey);
            }
        }
        //20250412
        $dbSource       = static::dbSource();
        $realFieldArr   = DbOperate::realFieldsArr($tableName, $dbSource);
        $dataSave       = Arrays::getByKeys($data, $realFieldArr);
        Debug::debug($tableName.'的doUpdateRam的$dataSave',$dataSave);
        //20220620:对多次的数据进行合并
        if(!isset($glUpdateData[$dbSource]) && !isset($glUpdateData[$dbSource][$tableName])){
            $glUpdateData[$dbSource][$tableName] = [];
        }
        // 20230730:如果还没写入数据库，则合并
        if(isset($glSaveData[$dbSource]) && isset($glSaveData[$dbSource][$tableName]) && Arrays::value($glSaveData[$dbSource][$tableName], $this->uuid)){
            $glSaveData[$dbSource][$tableName][$this->uuid] =  array_merge($glSaveData[$dbSource][$tableName][$this->uuid], $dataSave);
        } else {
            // 原来的逻辑
            $glUpdateData[$dbSource][$tableName][$this->uuid] = isset($glUpdateData[$dbSource][$tableName][$this->uuid]) 
                    ? array_merge($glUpdateData[$dbSource][$tableName][$this->uuid], $dataSave) 
                    : $dataSave;
        }

        // 设定内存中的值
        // return $dataSave;
        // $dataSave经获取器处理，对图片兼容不好
        return $data; 
    }
    
    
    /**
     * 20230807:更新并清理缓存
     */
    protected function doUpdateRamClearCache($data){
        $res = $this->doUpdateRam($data);
        //20230729
        static::dataCacheClear();
        return $res;
    }
    
}

<?php

namespace xjryanse\speedy\orm\baseTraits;

use Exception;
use xjryanse\speedy\orm\DbOperate;
use xjryanse\speedy\facade\DbOrm;
use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\logic\Debug;
use orm\system\SystemServiceMethodLog;
use orm\system\SystemAsyncTrigger;
/**
 * 主模型复用
 */
trait MainModelTrait {
    /**
     * 
     * @param type $data    原始数据，get 提取
     * @param type $uuid
     */
    protected static function delObjAttrs($data, $uuid){
        static::dealObjAttrsFunc($data, function($baseClass, $keyId, $property, $conf) use ($data, $uuid){
            $condition = Arrays::value($conf, 'condition' , []);
            if (Arrays::isMatch($data, $condition) && method_exists($baseClass, 'objAttrsUnSet')
                // 20240306：发现体检板块卡顿
                && $baseClass::inst($keyId)->objAttrsHasData($property)){
                $baseClass::inst($keyId)->objAttrsUnSet($property,$uuid);
            }
        });
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    /**********************************************************/
    
    
    
    // 魔术方法次数
    protected static $mgCallCount = 0;
    // 20230819:更新时，存储差异数组
    protected static $updateDiffs = [];

    /**
     * 20230711改写
     * @param type $methodName
     * @param type $arguments
     * @return type
     */
    public function __call($methodName, $arguments) {
        global $glMgCallCount;$glMgCallCountArr;
        $glMgCallCount ++;
        //首字母f，且第二个字母大写，表示字段
        if (!method_exists(static::class, $methodName)) {
            throw new Exception(static::class.'的'.$methodName . '不存在');
        }
        
        Debug::debug('__call', $methodName);
        $trace = debug_backtrace();
        //调用者方法
        $caller = $trace[1];
        // 20230731:记录调用者信息
        $logData['caller_class']    = Arrays::value($caller, 'class');
        $logData['caller_method']   = Arrays::value($caller, 'function');
        $logData['sort']            = $glMgCallCount;
        $glMgCallCountArr[]         = $glMgCallCount;
        // 此处增加统计次数逻辑；
        // 调用指定的函数
        $res = $this->$methodName(...$arguments);
        // 20230711:记录请求日志
        SystemServiceMethodLog::log(static::class, $methodName, $arguments, $res, $logData);
        return $res;
    }
    /**
     * 20230710:统计静态方法调用次数
     *     
     * @param type $methodName
     * @param type $arguments
     */
    public static function __callStatic($methodName, $arguments) {
        global $glMgCallCount;
        $glMgCallCount ++;

        if (!method_exists(static::class, $methodName)) {
            throw new Exception(static::class.'的'.$methodName . '不存在');
        }

        $trace = debug_backtrace();
        //调用者方法
        $caller                     = $trace[1];
        // 20230731:记录调用者信息
        $logData['caller_class']    = Arrays::value($caller, 'class');
        $logData['caller_method']   = Arrays::value($caller, 'function');
        $logData['sort']            = $glMgCallCount;
        Debug::debug('__callStatic执行', $methodName);
        // 此处增加统计次数逻辑；
        // 调用指定的函数
        $res = static::$methodName(...$arguments);
        // 20230711:记录请求日志
        SystemServiceMethodLog::log(static::class, $methodName, $arguments, $res, $logData);
        return $res;
    }

    /**
     * 条件给字段添加索引
     */
    protected static function condAddColumnIndex($con = []) {
        return true;
        // 无条件或非开发环境，不加索引
        if (!$con || !Debug::isDevIp()) {
            return false;
        }
        // 加索引动作
        foreach ($con as $conArr) {
            // 20240505:只有等号才加索引:一些比较短的还是不加吧
            if (is_array($conArr) && $conArr[1] == '=' && mb_strlen($conArr[2]) > 10) {
                DbOperate::addColumnIndex(static::inst()->getTable(), $conArr[0]);
            }
        }
    }

    /*     * ***公共保存【外部有调用】**** */

    /**
     * 【增强版】批量保存数据
     * @param type $dataArr
     */
    public static function saveAllX($dataArr, $isCover = false) {
        if (!$dataArr) {
            return false;
        }

        //20220621;解决批量字段不同步bug                
        $tableName = static::getTable();
        $saveArr = [];
        foreach($dataArr as $data){
            $keys = array_keys($data);
            sort($keys);
            ksort($data);
            $data = DbOperate::dataFilter($tableName, $data);
            $keyStr = md5(implode(',', $keys));
            $saveArr[$keyStr][] = $data;
        }
        // 20220621
        foreach($saveArr as $arr){
            $sql = DbOperate::saveAllSql($tableName, array_values($arr),[],$isCover);
            // 20250308：渠道
            $source = DbOperate::tableNameDbSource($tableName);
            DbOrm::query($sql, $source);
        }
        return true;
    }

    /**
     * 更新
     * @param array $data
     * @return type
     * @throws Exception
     */
    /*
    public function update(array $data) {
        //预保存数据
        return $this->commUpdate($data);
    }
    */
    /*
     * 设定字段的值
     * @param type $key     键
     * @param type $value   值
     */

    public function setField($key, $value) {
        return $this->update([$key => $value]);
    }


    /*
     * 设定字段的值
     * @param type $key         键
     * @param type $preValue    原值
     * @param type $aftValue    新值
     * @return type
     */

    public function setFieldWithPreValCheck($key, $preValue, $aftValue) {
        $info = $this->get(0);
        if ($info[$key] != $preValue) {
            throw new Exception(static::inst()->getTable() . '表' . $this->uuid . '记录'
                    . $key . '的原值不是' . $preValue);
        }
        $con[] = [$key, '=', $preValue];
        $con[] = ['id', '=', $this->uuid];
        $res = $this->update([$key => $aftValue]);

        //更新缓存
        return $res;
    }

    /**
     * 20230519:写入关联类库内存
     * @param type $data
     */
    protected static function pushObjAttrs($data){
        // dump('-----');
        // dump(static::inst()->getTable());
        // dump($data);
        static::dealObjAttrsFunc($data, function($baseClass, $keyId, $property, $conf) use ($data){
            $condition = Arrays::value($conf, 'condition' , []);
            // dump($baseClass);
            // dump('-----------');
            // dump($baseClass::inst($keyId)->objAttrsHasData($property));
            // 20230730
            // 20240313 改$baseClass::inst($keyId)->objAttrsHasData($property) 为 objAttrsHasQuery
            if (Arrays::isMatch($data, $condition) && method_exists($baseClass, 'objAttrsPush')
                // 20240306：发现体检板块卡顿
                && $baseClass::inst($keyId)->objAttrsHasQuery($property)){
                $baseClass::inst($keyId)->objAttrsPush($property,$data);
            }
        });
    }
    
    /**
     * 20230819：获取字段更新的差异部分
     * @param type $newData
     * @return type
     */
    protected function calUpdateDiffs($newData){
        $info = $this->get();
        // 20230815:获取有变化的内容
        // 20230815:校验增加字段判断
        static::$updateDiffs =  Arrays::diffArr($info, $newData);
        return static::$updateDiffs;
    }
    /**
     * 验证更新的这些字段是否包含指定字段数组中的一个
     */
    protected static function updateDiffsHasField(array $checkFields){
        if(!static::$updateDiffs){
            return false;
        }
        $diffKeys = array_keys(static::$updateDiffs);
        return array_intersect($diffKeys,$checkFields);
    }

    /**
     * 20230519:更新关联
     * @param type $data    更新数组内容
     * @param type $uuid    id单传
     */
    protected static function updateObjAttrs($data, $uuid){
        $updateDiffs = static::$updateDiffs;
        // dump($data);
        static::dealObjAttrsFunc($data, function($baseClass, $keyId, $property, $conf) use ($data,$uuid){
            $condition = Arrays::value($conf, 'condition' , []);
            // 但是不加好像有性能问题？？
            if (Arrays::isMatch($data, $condition) && method_exists($baseClass, 'objAttrsUpdate')){
                $baseClass::inst($keyId)->objAttrsUpdate($property,$uuid,$data);            
            }
        }, $updateDiffs);
    }
    /**
     * 处理关联属性的闭包函数
     * @param type $data
     * @param type $func
     * @param type $updateDiffs     20250812:更新偏差
     */
    private static function dealObjAttrsFunc($data,$func, $updateDiffs = []){
        $class = '\\'.static::class;
        $con[] = ['class', '=', $class];

        $lists = DbOperate::uniAttrConfArr($con);

        foreach($lists as $v){
            // project_id
            $keyField   = Arrays::value($v,'keyField');
            // DevProjectService
            $baseClass  = Arrays::value($v,'baseClass');
            // devProjectExt
            $property   = Arrays::value($v,'property');
            // 20250812:处理更新的问题:把原有属性数据从内存剔除
            if($updateDiffs && isset($updateDiffs[$keyField])){
                $preId = $updateDiffs[$keyField][0];
                $baseClass::inst($preId)->objAttrsUnSet($property,$data['id']);
            }

            $keyId      = $keyField ? Arrays::value($data,$keyField) : '';
            if($keyId && $baseClass && $property){
                // 20230519:调用闭包函数
                $func($baseClass, $keyId, $property, $v);
//                $baseClass::inst($keyId)->objAttrsUpdate($property,$uuid,$data);            
            }
        }
    }
    


    /**
     * 20220624:方法停用：用于过渡
     */
    public static function stopUse($method){
        throw new Exception($method.'方法停用');
    }
    
    /**
     * 20230416：关联数据
     * @param type $thingId
     */
    public static function uniDel($key,$keyIds){
        static::checkTransaction();
        $con[] = [$key,'in', $keyIds];
        return static::where($con)->delete();
    }
    
    /**
     * 20230425：清除字段
     * 应用场景：
     * 1、删除包车订单后，清关联表的订单编号字段
     * 2、删审批单后，清原始表审批单号字段
     * @param type $fieldName
     * @param type $fieldValue
     */
    public static function clearField($fieldName,$fieldValue){
        $con[] = [$fieldName,'=',$fieldValue];
        return static::where($con)->update([$fieldName=>'']);
    }
    /**
     * 20230519:模板消息发送
     * @param type $id          id
     * @param type $methodName  方法名
     * @return type
     */
    public static function doTemplateMsgSend($id, $methodName, $param = []){
        $tableName = static::getTable();
        $res = SendTemplateMsg::doMethodSend($tableName, $methodName, $id, $param);
        return $res;
    }
    
    /**
     * 20230531:执行触发器
     * @param type $triggerKey      钩子key
     * @param type $con             钩子条件
     * @param type $data            入参数据
     */
    public static function doTrigger($triggerKey, $con = [], $data = []){
        $triggers = DbOperate::triggerArr();
        $con[] = ['property','=',$triggerKey];
        $lists = Arrays2d::listFilter($triggers, $con);
        foreach($lists as $v){
            $dealClass  = Arrays::value($v, 'dealClass');
            $dealMethod = Arrays::value($v, 'dealMethod');
            if(class_exists($dealClass) && method_exists($dealClass, $dealMethod)){
                call_user_func([ $dealClass , $dealMethod], $data );
            }
        }
    }

    /**
     * 20241208:添加异步任务
     * @param type $methodName  例如：updateRam
     * @param type $param   :参数，建议越少越好
     */
    public function asyncAddTask($methodName, $param = []){
        $fromTable  = static::getTable(); 
        return SystemAsyncTrigger::addTask($methodName, $fromTable, $this->uuid, $param);
    }
    /**
     * 20250104：添加异步的更新任务
     * @param type $methodName
     * @param type $param
     */
    public function asyncAddUpdateTask(){
        $methodName = 'updateRam';
        $upParam    = ['status'=>1];
        return $this->asyncAddTask($methodName, $upParam);
    }
    
}

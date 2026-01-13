<?php
namespace xjryanse\speedy\orm\baseTraits;

use xjryanse\speedy\orm\DbOperate;
use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\logic\Strings;
use xjryanse\speedy\logic\ModelQueryCon;
/**
 * 模型联动字段复用
 */
trait ModelUniTrait {

    private static function privateUniFieldsArr(){
        // if(!property_exists($class, $property))
        if (!property_exists(static::class, 'uniFields')) {
            return [];
        }
        $prefix     = 'w_';
        $uniFields  = static::$uniFields;
        foreach($uniFields as &$v){
            $v['thisTable']     = static::getTable();
            $v['uniTable']      = $prefix. Arrays::value($v, 'uni_name');
            // 20230516：联动字段默认用id
            $v['uni_field']     = Arrays::value($v, 'uni_field','id');
            // 20230516：删除限制默认否
            $v['del_check']     = Arrays::value($v, 'del_check',false) ?1:0;
            // 删除消息
            $v['del_msg']       = Arrays::value($v, 'del_msg') ? : '数据在'.$v['thisTable'].'表'.$v['field'].'字段使用，不可删';

            // 20230608：是否处理关联属性中
            $v['in_list']       = Arrays::value($v, 'in_list', true) ?1:0;
            // 20230608：是否处理统计数据
            $v['in_statics']    = Arrays::value($v, 'in_statics', true) ?1:0;
            // 20230608：是否在列表中
            $v['in_exist']      = Arrays::value($v, 'in_exist', true) ?1:0;
            // 20230608:是否存在
            $v['existField']    = Arrays::value($v, 'exist_field') ? : DbOperate::fieldNameForExist($v['field']);
            // uniTable表的属性字段
            $classShortName     = (new \ReflectionClass(static::class))->getShortName();
            $v['property']      = Arrays::value($v, 'property') ? : lcfirst($classShortName);
            // 20251028
            $v['countField']    = Arrays::value($v, 'countField') ? : 'uni' . ucfirst($v['property']) . 'Count';
            // 匹配条件
            $conditionRaw       = Arrays::value($v, 'condition', []);
            $condStr            = json_encode($conditionRaw, JSON_UNESCAPED_UNICODE);
            $condJson           = Strings::dataReplace($condStr, $v);
            $condArr            = json_decode($condJson, JSON_UNESCAPED_UNICODE);
            $v['condition']     = $condArr ;

            // $v['dbSource']      = static::dbSource();
            // 没有用的字段
            unset($v['uni_name']);
            //
        }

        return $uniFields;
    }

    private static function privateUniRevFieldsArr(){
        // if(!property_exists($class, $property))
        if (!property_exists(static::class, 'uniRevFields')) {
            return [];
        }
        // $prefix     = config('database.prefix');
        $prefix     = 'w_';
        $uniFields  = static::$uniRevFields;
        foreach($uniFields as &$v){
            $v['thisTable']     = $prefix. Arrays::value($v, 'table');
            $v['uniTable']      = static::getTable();
            // 20230516：联动字段默认用id
            $v['uni_field']     = Arrays::value($v, 'uni_field','id');
            // 20230516：删除限制默认否
            $v['del_check']     = Arrays::value($v, 'del_check',false);
            // 删除消息
            $v['del_msg']       = Arrays::value($v, 'del_msg') ? : '数据在'.$v['thisTable'].'表'.$v['field'].'字段使用，不可删';
            // 20230608：是否处理关联属性中
            $v['in_list']       = Arrays::value($v, 'in_list', true);
            // 20230608：是否处理统计数据
            $v['in_statics']    = Arrays::value($v, 'in_statics', true);
            // 20230608：是否在列表中
            $v['in_exist']      = Arrays::value($v, 'in_exist', true);
            // 20230608:是否存在
            $v['existField']    = Arrays::value($v, 'exist_field') ? : DbOperate::fieldNameForExist($v['field']);
            // uniTable表的属性字段
            // $classShortName     = (new \ReflectionClass(static::class))->getShortName();
            $v['property']      = Arrays::value($v, 'property') ? : Strings::camelize(Arrays::value($v, 'table'));
            // 20251028
            $v['countField']    = Arrays::value($v, 'countField') ? : 'uni' . ucfirst($v['property']) . 'Count';
            // 匹配条件
            $conditionRaw       = Arrays::value($v, 'condition', []);
            $condStr            = json_encode($conditionRaw, JSON_UNESCAPED_UNICODE);
            $condJson           = Strings::dataReplace($condStr, $v);
            $condArr            = json_decode($condJson, JSON_UNESCAPED_UNICODE);
            $v['condition']     = $condArr ;            
            // $v['dbSource']      = static::dbSource();
            // 没有用的字段
            unset($v['uni_name']);
            ////
        }

        return $uniFields;
    }
    
    public static function uniFieldsArr(){
        // 正向字段
        $arr1 = static::privateUniFieldsArr();
        // 反向字段
        $arr2 = static::privateUniRevFieldsArr();
        return array_merge($arr1, $arr2);
    }
    /**
     * 20230609：根据查询条件，设定关联表
     *  结果形如：
     */
    public function uniSetTable($con = []){
        $list   = property_exists(static::class, 'uniFields') ? static::$uniFields : [];
        // 20240707尝试
        // $list   = static::uniFieldsArr();
        $tableRaw  = static::getTable();
        // 20231020:因别名冲突，故去除最外层括号后重新添加。
        $table = Strings::isStartWith($tableRaw, '(') 
                ? '('.Strings::getInnerBlank($tableRaw) .')'
                : $tableRaw;
        // 20230609:有关联查询
        $hasUni = false;
        foreach($list as $v){
            // 单字段
            $existField = Arrays::value($v, 'exist_field') ? : DbOperate::fieldNameForExist($v['field']);
            // 20231113:增加关联查询映射字段数组:见FinanceStaffFee
            $reflectFields  = Arrays::value($v, 'reflect_field') ? : [];
            // 一维数组：['hasStatement','hasSettle']
            $reflectKeys    = array_keys($reflectFields);
            // if(!ModelQueryCon::hasKey($con, $existField)){
            if(!ModelQueryCon::containKey($con, array_merge($reflectKeys,[$existField]))){
                continue;
            }
            $hasUni = true;
            // 【】设置别名
            $tA     = $table;

            $tB     = DbOperate::prefix().$v['uni_name'];
            $tBService = DbOperate::getService($tB);
            // 20231020:标识分表
            if(property_exists($tBService::inst(), 'isSeprate')  && $tBService::inst()::$isSeprate ){
                $tbTable = $tBService::inst()->setConTable();
                $tB = '('.Strings::getInnerBlank($tbTable) .')';
            }
            
            $kA     = ' a'.Strings::camelize($v['uni_name']);
            $kB     = ' b'.Strings::camelize($v['uni_name']);

            $keyArr = [];
            $keyArr[] = $kA.'.*';
            // 20231113
            if($reflectFields){
                foreach($reflectFields as $k1=>$v1){
                    $vKey       = Arrays::value($v1, 'key');
                    $nullVal    = Arrays::value($v1, 'nullVal');
                    $keyArr[] = 'ifnull('.$kB.'.'.$vKey.','.$nullVal. ') as `'.$k1.'`';         
                }
            }

            $where = '';
            // 处理存在字段
            if(ModelQueryCon::hasKey($con, $existField)){
                // 【拼装查询条件】
                $isExist = ModelQueryCon::parseValue($con, $existField);
                if($isExist){
                    $where = ' where '.$kB.'.'.$v['uni_field'].' is not null';
                } else {
                    $where = ' where '.$kB.'.'.$v['uni_field'].' is null';
                }
                // 字段写一个
                $keyArr[] = $isExist.' as `'.$existField.'`';                
            }

            // $sql    = "(select ".$kA.'.*,'.$isExist.' as `'.$existField.'` from '.$tA.' as '.$kA .' left join '.$tB.' as '.$kB.' on '.$kA.'.'.$v['field'].'='.$kB.'.'.$v['uni_field'].' '.$where.')';
            $sql    = '(select '.implode(',',$keyArr).' from '.$tA.' as '.$kA .' left join '.$tB.' as '.$kB.' on '.$kA.'.'.$v['field'].'='.$kB.'.'.$v['uni_field'].' '.$where.')';
            $table = $sql;
            // dump($sql);exit;
        }
        if($hasUni){
            $this->table = $table.' as mainTable';
        }

        return $this->table;
    }
}

<?php
namespace xjryanse\speedy\service;


use xjryanse\speedy\core\Orm;
use xjryanse\speedy\core\DbOrm;
use xjryanse\speedy\logic\Strings;
use xjryanse\speedy\logic\Arrays;


/**
 * 动态数据服务
 */
class DynDataService {

    /**
     * 从 Dynenum::columnSearchByService演化而来；
     * @param type $tableN
     * @param type $keyField
     * @param type $dataIds
     * @return type
     */
    public static function columnSearchByService($tableName, $keyField, $valueField, $dataIds, $cond = []){
        $cond[] = [$keyField,'in',$dataIds];
        $resSql = Orm::inst($tableName)->instInit()->where($cond)->select();

        $dbSource   = 'dbBusi';
        $resData    = DbOrm::inst($dbSource)->query($resSql);
        $res        = array_column($resData, $valueField, $keyField);
        return $res;
    }

    public static function dynData($dataArr, $fieldName, $tableName,$tableKey, $value){
        $dataIds        = $dataArr ? array_values(array_unique(array_column($dataArr, $fieldName))) : [];
        //20220131增加判断
        if(!$dataIds){
            return [];
        }
        $res = static::columnSearchByService($tableName, $tableKey, $value, $dataIds);
        return $res;
    }

    /**
     * 2023-01-16：动态数据列表拼装
     * @param type $dataArr
     * @param type $dynDatas
     * 'user_id'    =>'table_name=w_user&key=id&value=username'
     * 'goods_id'   =>'table_name=w_goods&key=id&value=goods_name'
     */
    public static function dynDataList($dataArr, $dynDatas){
        if(!$dynDatas){
            return [];
        }
        $optionArr = [];
        foreach($dynDatas as $fieldName=>$optionStr){
            $option     = Strings::equalsToKeyValue($optionStr);
            $tableName  = Arrays::value($option, 'table_name');
            $tableKey   = Arrays::value($option, 'key');
            $value      = Arrays::value($option, 'value');

            $optionArr[$fieldName] = static::dynData($dataArr, $fieldName, $tableName, $tableKey, $value);
        }
        return $optionArr;
    }

    
    
    /**
     * 页面表项目动态数组
     * @param type $pageItemId
     * @return type
     * @throws Exception
     */
    public static function universalItemTableDynArrs($pageItemId) {
        // 20250726:调优优化
        // $lists = static::pageItemAllList($pageItemId);
        
        $cTable = 'w_universal_item_table';
        $con    = [
            ['page_item_id','=',$pageItemId],
        ];

        $dbSource = 'dbBusi';
        $lists = DbOrm::inst($dbSource)->instInit()->table($cTable)->where($con)->select();

        //TODO: 以上数据可能需要解耦
        $cone[] = ['status', '=', 1];
        $cone[] = ['type', '=', 'dynenum'];
        $listEE = Arrays2d::listFilter($lists, $cone);
        // 20240411:防刁民
        foreach ($listEE as $v) {
            if (!$v['option']) {
                throw new Exception('未配置动态枚举参数' . $pageItemId . '-' . $v['name']);
            }
        }

        return array_column($listEE, 'option', 'name');
    }
    
    
    
    
    

}

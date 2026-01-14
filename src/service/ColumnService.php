<?php

namespace xjryanse\speedy\service;


use xjryanse\speedy\facade\Cache;
use xjryanse\speedy\logic\Debug;
use orm\system\SystemColumn;
use orm\system\SystemColumnList;

use Exception;
use think\Db;
use xjryanse\speedy\logic\ModelQueryCon;
use xjryanse\speedy\orm\DbOperate;

use xjryanse\speedy\core\DbOrm;


/**
 * 字段逻辑
 */
class ColumnService {
        /**
     * 获取搜索字段
     * 20251226;
     */
    public static function tableSearchFields($tableName) {
        $dbSource   = 'dbBusi';
        $cTable1    = 'w_system_column';

        $cone   = [['table_name','=',$tableName]];
        $info       = DbOrm::inst($dbSource)->instInit()->table($cTable1)->where($cone)->find();
        if(!$info){
            // 20260105：没有配置认为没有搜索字段就可以
            return [];
            // throw new Exception('w_system_column没有配置数据表'.$tableName);
        }

        $cTable = 'w_system_column_list';
        $con    = [
            ['column_id','=',$info['id']],
            ['status','=',1]
        ];
        $lists = DbOrm::inst($dbSource)->instInit()->table($cTable)->where($con)->select();
        // todo:以上的业务逻辑，可能需要剥离到配置微服务：：：：：：：：：：：：：：：：
        
        $searchFields = [];
        foreach ($lists as $v) {
            if ($v['search_type'] && $v['search_type'] != '-1') {
                $searchFields[$v['search_type']][] = $v['name'];
            }
        }
        
        return $searchFields;
    }
    
    
    
    // use \xjryanse\traits\TreeTrait;
    // use \xjryanse\traits\DebugTrait;

    public static function setDbSource($dbSource){
        SystemColumn::setDbSource($dbSource);
        SystemColumnList::setDbSource($dbSource);
    }
    
    /**
     * 取默认表
     * @param type $controller  控制器
     * @param type $tableKey    表键
     * @param type $companyId   公司id
     * @param type $cateFieldValue  可以是字符串或数组，如数组，按key取值
     * @return type
     */
    public static function defaultColumn($tableName) {
        $con[] = ['table_name', '=', $tableName];

        $columnInfo = SystemColumn::staticConFind($con);
        $columnId = $columnInfo ? $columnInfo['id'] : "";

        return $columnId ? static::getById($columnId) : [];
    }

    /**
     * 获取搜索字段
     */
    public static function getSearchFields($columnInfo, $queryType = 'where') {
        $searchFields = [];
        if ($columnInfo['listInfo']) {
            foreach ($columnInfo['listInfo'] as $v) {
                if ($v['search_type'] != '-1') {
                    $searchFields[$v['search_type']][] = $v['name'];
                }
            }
        }
        return $searchFields;
    }

    /**
     * 将键值对参数，转拼接为mysql 模型类查询条件
     */
    public static function paramToQueryCon($tableName, $param) {
        $columnInfo = static::tableNameColumn($tableName);
        $whereFields = static::getSearchFields($columnInfo);
        //【通用查询条件】
        $con = ModelQueryCon::queryCon($param, $whereFields);
        return $con;
    }

    /**
     * 可以放到表中查询的字段
     * @param type $columnId
     * @return type
     */
    public static function listFields($columnId) {
        //【1】状态为开的字段
        $con[]      = ['column_id', '=', $columnId];
        $con[]      = ['status', '=', 1];
        // $res = SystemColumnList::mainModel()->where($con)->cache(86400)->column('distinct name');
        $nameArr    = SystemColumnList::staticConColumn('name');
        $res        = array_unique($nameArr);
        //【2】数据表有的字段
        $tableName      = SystemColumn::idFv($columnId,'table_name');
        $tableColumns   = DbOperate::columns($tableName);
        //数据表已有字段
        $tableFields    = array_column($tableColumns, 'Field');
        //【3】取交集
        return array_intersect($res, $tableFields);
    }

    /**
     * 获取搜索字段
     */
    public static function getImportFields($columnInfo) {
        $importFields = [];
        foreach ($columnInfo['listInfo'] as $v) {
            $importFields[$v['label']] = $v['name'];
        }
        return $importFields;
    }

    /**
     * 传一个表名，拿到默认的column信息
     * @param type $tableName   表名
     * @param type $fields      表字段
     * @param type $methodKey   方法key 
     * @param type $data        用于联动过滤的数据
     * @return type
     */
    public static function tableNameColumn($tableName, $fields = '', $methodKey = '', $data = []) {
        $con[] = ['table_name', '=', $tableName];
        $columnInfo = SystemColumn::staticConFind($con);
        $columnId = $columnInfo ? $columnInfo['id'] : "";

        return static::getById($columnId, $fields, '', $methodKey, $data);
    }

    public static function tableHasRecord($tableName) {
        $con[] = ['table_name', '=', $tableName];
        $info = SystemColumn::find($con, 86400);
        return $info;
    }

    /**
     * 信息
     */
    public static function info($id) {
        $info = SystemColumn::inst($id)->staticGet();
        return static::getDetail($info);
    }

    /*
     * 取详细信息
     * @param type $info        表信息
     * @param type $fields      字段数组
     * @param type $conField    字段过滤信息
     * @param type $methodKey    方法id
     * @return boolean
     */

    private static function getDetail($info, $fields = '', $conField = []) {
        if (!$info) {
            return false;
        }
        //是否只取某些字段
        if ($fields) {
            if (!is_array($fields)) {
                $fields = explode(',', $fields);
            }
            $conField[] = ['name', 'in', $fields];
        }
        //字段列
        $con1[] = ['column_id', '=', $info['id']];
        $con1[] = ['status', '=', 1];

        $info['listInfo'] = SystemColumnList::staticConList($conField ? array_merge($conField, $con1) : $con1);

        return $info;
    }

    //字段转换

    /**
     * 
     * @param type $res
     * @param type $data    用于联动的数据
     * @return type
     */
    private static function scolumnCov(&$res, $data = []) {
        if (!isset($res['listInfo'])) {
            return $res;
        }
        //字段
        foreach ($res['listInfo'] as $k => &$v) {
            //冗余字段，方便前端使用
            $v['table_name'] = $res['table_name'];
            //选项
            //数据中，取出与当前键名一致的id，用于动态枚举少量筛选数据
            $tempColumnData = $data ?: [];
            if (isset($data[$v['name']])) {
                $ids = $data[$v['name']];
                $tempColumnData['id'] = $ids;
            }
            $v['option'] = SystemColumnList::getOption($v['type'], $v['option'], $tempColumnData);
            //查询条件
            $v['show_condition'] = json_decode($v['show_condition'], JSON_UNESCAPED_UNICODE);

            //联表数据
            if ($v['type'] == 'union') {
                //参数
                $v['table_info'] = static::tableNameColumn($v['option']['table_name'], isset($v['option']['fields']) ? $v['option']['fields'] : []);
            }
        }
        return $res;
    }

    /**
     * 生成表信息
     */
    public static function generate($table) {
        if (static::tableHasRecord($table)) {
            throw new Exception('数据表已存在，不支持重复生成');
        }
        //取数据表字段
        $columns = Db::table('information_schema.columns')->field('column_name')->where('table_name', $table)->column('column_name');
        if (!$columns) {
            throw new Exception('数据表不存在，或没有字段，不能生成');
        }

        $data['controller'] = DbOperate::getController($table);
        $data['table_key'] = DbOperate::getTableKey($table);
        $data['table_name'] = $table;
        $res = SystemColumn::save($data);
        //字段
        $tmp = [];
        $sort = 100;    //字段排序值
        $hideKeys = ['sort', "create_time", "update_time", "status", "has_used", "is_lock", "is_delete", "creater", "updater", "app_id", "company_id"];
        foreach ($columns as $k => $v) {
            $tmp[$k]['column_id'] = $res['id'];
            $tmp[$k]['name'] = $v;
            $tmp[$k]['label'] = $v;
            $tmp[$k]['sort'] = $sort;
            $sort += 100;   //排序
            $tmp[$k]['type'] = ($v == 'id') ? 'hidden' : 'text';   //id隐藏域
            $tmp[$k]['is_add'] = (in_array($v, array_merge(['id'], $hideKeys))) ? 0 : 1;
            $tmp[$k]['is_edit'] = (in_array($v, $hideKeys)) ? 0 : 1;
            $tmp[$k]['is_list'] = (in_array($v, array_merge(['id'], $hideKeys))) ? 0 : 1;
            //TODO优化
            SystemColumnList::save($tmp[$k]);
        }

        return $res;
    }

    /**
     * 表名，查询条件
     * @param type $tableName
     * @param type $con
     */
    public static function dynamicColumn($tableName, $field, $key, $con = []) {
        //替换资源链接
        $list = Db::table($tableName)->where($con)->cache(86400)->column($field, $key);
        return $list;
    }

    /**
     * 最新20201228
     * @param type $columnId        字段id
     * @param type $fields          指定字段
     * @param type $cateFieldValue  分类值（用于不同表取不同的字段）
     * @param type $methodKey        方法id
     * @return type
     */
    public static function getById($columnId, $fields = [], $data = []) {
        $key = Cache::cacheKey($columnId, $fields, $data);
        //20220130，增加缓存
        return Cache::funcGet('ColumnService_getById' . $key, function () use ($columnId, $fields, $data) {
                    //$info   = SystemColumn::inst( $columnId )->get( 86400 );
                    $info = SystemColumn::inst($columnId)->staticGet();
                    Debug::debug('ColumnService::getById', $info);
                    $con = [];

                    $info2 = static::getDetail($info, $fields, $con);
                    //循环
                    //字段转换
                    $res = static::scolumnCov($info2, $data);
                    return $res;
                });
    }

    /**
     * 导入数据转换
     */
    public static function getCovData($columnInfo) {
        $covFields = [];
        foreach ($columnInfo['listInfo'] as $v) {
            //TODO优化
            if ($v['type'] == 'enum') {
                $covFields[$v['name']] = array_flip($v['option']);
            }
        }
        return $covFields;
    }
}

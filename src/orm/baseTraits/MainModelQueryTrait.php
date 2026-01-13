<?php

namespace xjryanse\speedy\orm\baseTraits;

use xjryanse\speedy\orm\DbOperate;
use service\AuthService;
use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\logic\Strings;
use xjryanse\speedy\logic\Arrays2d;
use xjryanse\speedy\logic\Debug;
use xjryanse\speedy\logic\Datetime;
use xjryanse\speedy\logic\DataCheck;
use orm\system\SystemColumn;
use orm\system\SystemColumnList;
use orm\universal\UniversalPageItem;
use orm\universal\UniversalItemTable;
// use orm\system\SystemTableCacheTimeService;
use xjryanse\speedy\logic\SnowFlake;
use xjryanse\speedy\facade\Request;
use xjryanse\speedy\facade\DbOrm;
use xjryanse\speedy\facade\Cache;
use Exception;

/**
 * 主模型复用(只放查询方法)
 * 20230805
 */
trait MainModelQueryTrait {

    /**
     * 已使用
     * 带条件global
     * @describe 使用listSetUUData替代
     */
    public static function listWithGlobal($con) {
        $tableName  = static::inst()->getTable();
        $dbSource   = static::inst()->dbSource();
        $lists      = static::inst()->reset()->where($con)->select();
        // 处理内存中的新增数据
        $glSavesRaw = DbOperate::tableGlobalSaveData($tableName, $dbSource);
        if ($glSavesRaw) {
            // 将id key整合为序号
            $glSaves    = array_values($glSavesRaw);
            $listsN     = Arrays2d::listFilter($glSaves, $con);
            foreach ($listsN as $vn) {
                $lists[] = $vn;
            }
        }
        // 处理内存中的更新、删除数据
        $glUpdates      = DbOperate::tableGlobalUpdateData($tableName, $dbSource);
        $glDeleteIds    = DbOperate::tableGlobalDeleteIds($tableName, $dbSource);
        foreach ($lists as $k => $v) {
            // 20240305:更新在内存中未提交的数据
            $thisVal    = Arrays::value($glUpdates, $v['id'], []);
            if ($thisVal) {
                foreach ($thisVal as $key => $value) {
                    $v[$key] = $value;
                }
            }
            //20230807：先处理歪写（更新时）
            if (!static::inst($v['id'])->uuData) {
                static::inst($v['id'])->setUuData($v, true);  //强制写入
            }
            // 20240325
            if (in_array(Arrays::value($v, 'id'), $glDeleteIds)) {
                unset($lists[$k]);
            }
        }

        return array_values($lists);
    }
    
    /*【使用标记分割线】*********************************************/
    /**
     * 20250209：条件取单条；替代原来的find
     */
    public static function conFind($con = [],$field = "*"){
        $lists = static::listWithGlobal($con);
        return $lists ? $lists[0] : null;
    }
    /**
     * 20250209：条件取单条；替代原来的count
     */
    public static function conCount($con = []){
        $res = static::inst()->reset()->where($con)->count();
        return $res;
    }
    
    // 1查询列表；
    public static function lists(array $con = [], $orderBy='', $field = "*", $limit = 0 ){    
        // 20250207
        // 一般用于设置分表
        if (method_exists(static::class, 'preListDeal')) {
            static::preListDeal($con);
        }
        if(static::hasField('company_id')){
            // 20250204:多租户过滤
            $con[] = ['company_id','=',session(SESSION_COMPANY_ID)];
        }
        if (static::hasField('is_delete')) {
            $con[] = ['is_delete', '=', 0];
        }
        // 20250912:增加reset
        $resR = static::inst()->reset()->where($con)->order($orderBy)->field($field)->limit($limit)->select();
        // 20250529
        $res = static::dataDealAttr($resR);
        if ($field == "*") {
            foreach ($res as &$v) {
                static::inst($v['id'])->setUuData($v, true);  //强制写入
            }
        }
        
        return $res;
    }
    
    
    protected static function commLists($con = [], $order = '', $field = "*", $cache = 2) {
        static::stopUse('------');
        $conAll = array_merge($con, static::commCondition());
        if (!$order && static::hasField('sort')) {
            $order = "sort";
        }

        //字段加索引
        static::condAddColumnIndex($con);
        $res = static::where($conAll)->order($order)->field($field)->cache($cache)->select();
        // 查询出来了直接存
        if ($field == "*") {
            foreach ($res as &$v) {
                static::inst($v['id'])->setUuData($v, true);  //强制写入
            }
        }
        return $res;
    }
    //公共的数据过滤条件
    protected static function commCondition($withDataAuth = true) {
        $con = session(SESSION_USER_ID) && $withDataAuth 
                ? AuthService::dataCon(session(SESSION_USER_ID), static::getTable()) 
                : AuthService::dataCon(session(SESSION_USER_ID), static::getTable(), true);  //不带数据权限情况下，只取严格模式的权限
        //customerId 的session
        //客户id  有bug20210323
        /*
        if (static::hasField('customer_id') && session(SESSION_CUSTOMER_ID)) {
//            $con[] = ['customer_id','=',session(SESSION_CUSTOMER_ID)];
        }
         */
        //公司隔离
        if (static::hasField('company_id') && session(SESSION_COMPANY_ID)) {
            $con[] = ['company_id', '=', session(SESSION_COMPANY_ID)];
        }
        //删除标记
        if (static::hasField('is_delete')) {
            $con[] = ['is_delete', '=', 0];
        }
        return $con;
    }
    /*
    public static function lists($con = [], $order = '', $field = "*", $cache = 1) {
        //如果cache小于-1，表示外部没有传cache,取配置的cache值
        // $cache = $cache < 0 ? static::defaultCacheTime() : $cache;
        // 20240311
        if (method_exists(static::class, 'preListDeal')) {
            static::preListDeal($con);
        }

        return static::commLists($con, $order, $field, $cache)->each(function ($item, $key) {
                    //额外添加详情信息：固定为extraDetail方法
                    if (method_exists(static::class, 'extraDetail')) {
                        static::extraDetail($item, $item['id']);
                    }
                });
    }
    */
    
    
    // 2查询分页；
    
    // 3查询单条；
    
    // 4计数；
    
    
    
    
    
    
    /**
     * 20230609:提取关联的删除数组
     */
    public static function uniExistFields() {
        if (!property_exists(static::class, 'uniFields')) {
            return [];
        }
        $fields = static::$uniFields;
        $existFields = [];
        foreach ($fields as $v) {
            // $existFields[] = DbOperate::fieldNameForExist($v['field']);
            // 20230726
            $existFields[] = Arrays::value($v, 'exist_field') ?: DbOperate::fieldNameForExist($v['field']);
        }
        return $existFields;
    }
    
    /**
     * 20231019:源表（是分表的转为源表）
     * @return type
     */
    public static function getRawTable() {
        $table = static::getTable();

        $arr = explode('_', $table);
        if (Datetime::isYear(end($arr))) {
            array_pop($arr);
        }
        return implode('_', $arr);
    }
    
    /**
     * 分页的查询
     * @param type $con
     * @param type $order
     * @param type $perPage
     * @return type
     */
    public static function paginate($con = [], $order = '', $perPage = 10, $having = '', $field = "*", $withSum = false) {
        // 20240505:自动添加索引，让系统越跑越快
        static::condAddColumnIndex($con);

        $res = static::paginateX($con, $order, $perPage, $having, $field, $withSum);
        // 关联表id，提取相应的字段
        $uTableId = Request::param('uTableId');
        if ($uTableId && UniversalPageItem::inst($uTableId)->fv('field_filter')) {
            $fieldArr = UniversalItemTable::pageItemFieldsForDataFilter($uTableId);
            if ($fieldArr) {
                $res['data'] = Arrays2d::getByKeys($res['data'], $fieldArr);
            }
        }

        return $res;
    }
    /**
     * id数组
     * @param type $con
     * @return type
     */
    public static function ids($con = [], $order = '') {
        $conAll = array_merge($con, static::commCondition());
        //字段加索引
        static::condAddColumnIndex($con);

        $inst = static::inst()->where($conAll);
        if ($order) {
            $inst->order($order);
        }
        return $inst->cache(1)->column('id');
    }
    
    
    /**
     * 20250204：提取模型指定id下指定值
     */
    public static function idFv($id, $field){
        $info = static::inst($id)->get();
        return Arrays::value($info, $field);
    }

    public function fv($field){
        $info = $this->get();
        return Arrays::value($info, $field);
    }
    
    
    
    
    
    /*【以上已确认可用】*************************************/
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    //20220617:考虑get没取到值的情况，可以不用重复查询
    protected $hasUuDataQuery = false;
    protected $uuData = [];

    /**
     * 20220921 主模型带where参数
     */
    


    /**
     * 预保存数据
     * @useFul 1
     */
    protected static function preSaveData(&$data) {
        return static::commPreSaveData($data);
    }

    protected static function commPreSaveData(&$data) {
        Debug::debug('预保存数据$data', $data);
        if (!isset($data['id']) || !$data['id']) {
            $data['id'] = static::newId();
        }
        if (session(SESSION_COMPANY_ID) && !isset($data['company_id']) && static::hasField('company_id')) {
            $data['company_id'] = session(SESSION_COMPANY_ID);
        }
        if (session(SESSION_USER_ID) && !isset($data['creater']) && static::hasField('creater')) {
            $data['creater'] = session(SESSION_USER_ID);
        }
        //数据来源
        if (session(SESSION_SOURCE) && !isset($data['source']) && static::hasField('source')) {
            $data['source'] = session(SESSION_SOURCE);
        }
        //20220324:部门id()
        if (session(SESSION_DEPT_ID) && !isset($data['dept_id']) && static::hasField('dept_id')) {
            $data['dept_id'] = session(SESSION_DEPT_ID);
        }
        // 20221026：create_time????
        if (!isset($data['create_time']) || !$data['create_time']) {
            $data['create_time'] = date('Y-m-d H:i:s');
        }
        $data['update_time']    = date('Y-m-d H:i:s');
        $data['status']         = 1;
        $data['is_delete']      = 0;

        return $data;
    }

    /*
     * 数据库查询
     * @useFul 1
     * @describe 解决oss图片动态路径封装
     * @createTime 2023-06-21 13:52:00
     */
    public static function selectDb($con = [], $order = "", $field = "", $hidden = []) {
//        $tableName = static::getTable();
//        // 20250818：替换
//        $inst = DbOrm::table($tableName);
//        
        $inst = static::inst()->reset();
        // 20240312
        if (static::hasField('is_delete')) {
            $con[] = ['is_delete', '=', 0];
        }
        if ($con) {
            //注意：如果有分表，这里有设置分表的动作
            $inst->where($con);
        }
        if ($order) {
            $inst->order($order);
        }
        if ($field) {
            $inst->field($field);
        }
        if ($hidden) {
            $inst->hidden($hidden);
        }
        
        $data = $inst->select();
        return $data;
    }

    /**
     * 20220305
     * 替代TP框架的select方法，在查询带图片数据上效率更高
     * @param type $inst    组装好的db查询类
     */
    public static function selectX($con = [], $order = "", $field = "", $hidden = []) {
        // 20230621：使用Db方法从数据库中查询
        $data = static::selectDb($con, $order, $field, $hidden);
        // 20230621：模型获取器处理数据
        return static::dataDealAttr($data);
    }

    /**
     * 20230429：增强的筛选，自动判断是否有静态。
     * @useFul 1
     */
    public static function selectXS($con = [], $order = "", $field = "", $hidden = []) {
        if (method_exists(static::class, 'staticConList')) {
            $lists = static::staticConList($con);
        } else {
            $lists = static::selectX($con, $order, $field, $hidden);
        }
        return $lists;
    }

    /**
     * 字段名取值
     * @param type $fieldName   字段名
     * @param type $default     默认值
     * @return type
     */
    public function fieldValue($fieldName, $default = '') {
        //如果是定值；有缓存取缓存；无缓存再从数据库取值
        if ((property_exists(static::class, 'fixedFields') && in_array($fieldName, static::$fixedFields))) {
            $tableName = static::getTable();
            $cacheKey = $tableName . '-' . $this->uuid . '-' . $fieldName;
            return Cachex::funcGet($cacheKey, function () use ($fieldName, $default) {
                        return $this->fieldValueFromDb($fieldName, $default);
                    });
        } else {
            return $this->fieldValueFromDb($fieldName, $default);
        }
    }

    /**
     * 从数据库取新值
     */
    private function fieldValueFromDb($fieldName, $default = '') {
        //20220306；配置式缓存
        if (method_exists(static::class, 'staticGet')) {
            $info = $this->staticGet();
        } else {
            $info = $this->get();
        }
        return Arrays::value($info, $fieldName, $default);
    }

    /**
     * 获取f开头的驼峰方法名字段信息
     * @param type $functionName  方法名，一般__FUNCTION__即可
     * @param type $prefix          前缀
     * @return type
     */
    public function getFFieldValue($functionName, $prefix = "f_") {
        //驼峰转下划线，再去除前缀
        $pattern = '/^' . $prefix . '/i';
        $fieldName = preg_replace($pattern, '', Strings::uncamelize($functionName));
        //调用MainModelTrait中的字段值方法
        return $this->fieldValue($fieldName);
    }


    /**
     * 20221104：查询结果即数组
     * @param type $con
     * @param type $order
     * @param type $field
     * @param type $cache
     * @return type
     */
    public static function listsArr($con = [], $order = '', $field = "*", $cache = -1) {
        $lists = static::lists($con, $order, $field, $cache);
        return $lists ? $lists->toArray() : [];
    }

    /**
     * 查询列表，并写入get
     * @useFul 1
     * @param type $con
     */
    public static function listSetUudata($con = [], $master = false) {
        global $glSaveData, $glUpdateData, $glDeleteData;
//  ["w_order_bao_bus_driver"] =&gt; array(1) {
//    [5572710300365492224] =&gt; array(2) {
//      ["distribute_prize"] =&gt; string(2) "88"
//      ["update_time"] =&gt; string(19) "2024-03-05 16:26:09"
//    }
//  }
//  {
//  ["w_bus_fix_item"] =&gt; array(1) {
//    [0] =&gt; string(19) "5583986115896299520"
//  }
//}
        // 20240224：增加分表逻辑（体检板块）
        if (property_exists(static::class, 'isSeprate') && static::$isSeprate) {
            static::inst()->setConTable($con);
        }

        $lists = static::selectXS($con);

        // 20240305:更新在内存中未提交的数据
        // 包车发现更新驾驶员金额，财务端不同步
        //写入内存
        $tableName = static::getTable();
        // 20240319
        if ($glSaveData) {
            $glSavesRaw = Arrays::value($glSaveData, $tableName, []);
            // 将id key整合为序号
            $glSaves = array_values($glSavesRaw);
            $listsN = Arrays2d::listFilter($glSaves, $con);
            foreach ($listsN as $vn) {
                $lists[] = $vn;
            }
        }

        foreach ($lists as $k => $v) {
            // 20240305:更新在内存中未提交的数据
            $glUpdates = Arrays::value($glUpdateData, $tableName, []);
            $thisVal = Arrays::value($glUpdates, $v['id'], []);
            if ($thisVal) {
                foreach ($thisVal as $key => $value) {
                    $v[$key] = $value;
                }
            }
            //20230807：先处理歪写（更新时）
            if (!static::inst($v['id'])->uuData) {
                static::inst($v['id'])->setUuData($v, true);  //强制写入
            }
            // 20240325
            $glDelIds = Arrays::value($glDeleteData, $tableName, []);
            if (in_array(Arrays::value($v, 'id'), $glDelIds)) {
                unset($lists[$k]);
            }
        }

        return $lists;
    }

    /**
     * 20220919动态数组列表
     */
    public static function dynDataList($dataArr) {
        $columnId = SystemColumn::tableNameGetId(static::getTable());
        $dynFields = SystemColumnList::columnTypeFields($columnId, 'dynenum');
        $dynDatas = [];
        foreach ($dynFields as $key) {
            $dynDatas[$key] = array_unique(array_column($dataArr, $key));
        }
        Debug::debug('commPaginate 的 dynDataList 的 $columnId', $columnId);
        Debug::debug('commPaginate 的 dynDataList 的 $dynDatas', $dynDatas);
        $dynDataList = SystemColumnList::sDynDataList($columnId, $dynDatas);
        return $dynDataList;
    }

    /**
     * 使用自己封装的分页查询方法（框架自带方法有性能问题）
     * @param type $con
     * @param string $order
     * @param type $perPage
     * @param type $having
     * @param type $field
     * @return type
     */
    public static function paginateX($con = [], $order = '', $perPage = 10, $having = '', $field = "*", $withSum = false) {
        //默认带数据权限
        $conAll = array_merge($con, static::commCondition());
        //如果有额外的数据过滤条件限定方法
        if (method_exists(static::class, 'extraDataAuthCond')) {
            $conAll = array_merge($conAll, static::extraDataAuthCond());
        }
        // 查询条件单拎；适用于后台管理（客户权限，业务员权限）
        return static::paginateRaw($conAll, $order, $perPage, $having, $field, $withSum);
    }

    /**
     * 【已用】raw方法，解决有些不需要数据权限的场景：比如web端
     * @param type $conAll
     * @param type $order
     * @param type $perPage
     * @param type $having
     * @param string $field
     * @param type $withSum
     * @return type
     */
    public static function paginateRaw($conAll = [], $order = '', $perPage = 10, $having = '', $field = "*", $withSum = false) {
        if (method_exists(static::class, 'extraDetails')) {
            $field = 'id';
        }
        // 20230609:增加关联存在字段的查询
        $baseTable = 'test';
        if (method_exists(static::class, 'uniSetTable')) {
            $baseTable = static::inst()->uniSetTable($conAll);
        }

        // 一定要放在setCustTable前面
        $columnId = SystemColumn::tableNameGetId(static::getRawTable());
        $page = Request::param('page')?:1;
        $start = (intval($page) - 1) * intval($perPage);
        // 定制数据表查询视图的方法
        if (method_exists(static::class, 'setCustTable')) {
            //20211015这种一般都是业务比较复杂的，从主库进行查询
            //TODO是否可以优化？？
            if (method_exists(static::class, 'setCustIdTable')) {
                $resp['table'] = static::setCustIdTable($conAll);
                $res = static::master()->order($order)->field($field)->limit($start, intval($perPage))->select();
                // 定制数据统计方法
                $total = method_exists(static::class, 'custCount') ? static::custCount($conAll) : static::count(1);
            } else {
                $resp['table'] = static::setCustTable($conAll);
                $res = static::master()->order($order)->field($field)->limit($start, intval($perPage))->select();
                // 定制数据统计方法
                $total = method_exists(static::class, 'custCount') ? static::custCount($conAll) : static::count(1);
            }
        } else {
            // 20241206:有分表的
            $res = static::inst()->where($conAll)->order($order)->field($field)->limit(intval($perPage), $start)->select();
            // 20231020:便利调试
            //20220619：如果查询结果数小于分页条数，则结果数即总数
            $total = $page == 1 && count($res) < $perPage ? count($res) : static::countCache($conAll);
            // : static::where($conAll)->count(1);
        }
        // dump(static::dbInst()->getSqlArr());
        // 采用跟TP框架一样的数据格式
        $resp['data'] = $res;
        //额外数据信息；上方取了id后，再此方法内部根据id进行第二次查询
        //（逻辑比较复杂，但大表数据效率较高）
        if (method_exists(static::class, 'extraDetails')) {
            $extraDetails = static::extraDetails(array_column($resp['data'], 'id'));
            //id 设为键
            $extraDetailsObj = Arrays2d::fieldSetKey($extraDetails, 'id');
            foreach ($resp['data'] as &$v) {
                $v = isset($extraDetailsObj[$v['id']]) ? $extraDetailsObj[$v['id']] : $v;
            }
        }
        // 关联字段的键值对封装（）
        /*         * *********** 动态枚举 ************ */
        $resp['dynDataList'] = SystemColumnList::getDynDataListByColumnIdAndData($columnId, $resp['data']);
//
//        $resp['$dynFields']     = $dynFields;
//        $resp['$columnId']      = $columnId;
//        $resp['$dynDatas']      = $dynDatas;
        // 采用跟TP框架一样的数据格式
        $resp['current_page'] = $page;
        $resp['total'] = $total;
        $resp['per_page'] = intval($perPage);
        $resp['last_page'] = ceil($resp['total'] / intval($perPage));
        // 是否展示统计数据
        $resp['withSum'] = $withSum ? 1 : 0;
        /**
         * 2020303,新增带求和字段
         */
        if ($withSum && $resp['data']) {
            $sumFields = SystemColumnList::sumFields($columnId);
            if ($sumFields) {
                $fieldStr = DbOperate::sumFieldStr($sumFields);
                $resp['sumData'] = static::inst()->where($conAll)->field($fieldStr)->find();
            } else {
                // 20220610:增加空数据时输出，避免前端报错
                $resp['sumData'] = [];
            }
        }
        // 20250912:初始化类库：
        static::inst()->reset();

        return $resp;
    }

    /**
     * 自带当前公司的列表查询
     * @param type $con
     * @return type
     */
    public static function listsCompany($con = [], $order = '', $field = "*") {
        //公司id
        if (static::hasField('company_id') && session(SESSION_COMPANY_ID)) {
            $con[] = ['company_id', '=', session(SESSION_COMPANY_ID)];
        }
        $conAll = array_merge($con, static::commCondition());

        if (!$order && static::hasField('sort')) {
            $order = "sort";
        }
        //字段加索引
        static::condAddColumnIndex($conAll);

        return static::where($conAll)->order($order)->field($field)->cache(2)->select();
    }

    /**
     * 带详情的列表
     * @param type $con
     */
    public static function listsInfo($con = []) {
        return static::lists($con);
    }

    /*
     * 按字段值查询数据
     * @param type $fieldName   字段名
     * @param type $fieldValue  字段值
     * @param type $con         其他条件
     * @return type
     */

    public static function listsByField($fieldName, $fieldValue, $con = []) {
        $con[] = [$fieldName, '=', $fieldValue];
        return static::lists($con, '', '*', 0);    //无缓存取数据
    }


    /**
     * 根据字段的值，提取id；
     * 如单号，提取明细号
     * @param type $fieldName
     * @param type $value
     */
    public static function fieldGetIds($fieldName, $value) {
        $con[] = [$fieldName, '=', $value];
        return static::where($con)->cache(1)->column('id');
    }

    /**
     * 根据条件返回字段数组
     * @param type $field   字段名
     * @param type $con     查询条件
     * @return type
     */
    /*
    public static function column($field, $con = []) {
        if (static::hasField('app_id')) {
            $con[] = ['app_id', '=', session(SESSION_APP_ID)];
        }
        //TODO会有bug
        if (static::hasField('company_id') && session(SESSION_COMPANY_ID)) {
            $con[] = ['company_id', '=', session(SESSION_COMPANY_ID)];
        }
        //字段加索引
        static::condAddColumnIndex($con);

        return static::where($con)->cache(2)->column($field);
    }
*/
    /**
     * 条件计数
     * @param type $con
     * @return type
     */
    /*
    public static function count($con = []) {
        if (static::hasField('app_id')) {
            $con[] = ['app_id', '=', session(SESSION_APP_ID)];
        }
        if (static::hasField('is_delete')) {
            $con[] = ['is_delete', '=', 0];
        }
        //20220120增加公司隔离，是否有bug？？
        if (static::hasField('company_id') && session(SESSION_COMPANY_ID)) {
            $con[] = ['company_id', '=', session(SESSION_COMPANY_ID)];
        }

        //字段加索引
        static::condAddColumnIndex($con);

        return static::where($con)->count();
    }
     * 
     */

    /**
     * 条件计数
     * @param type $con
     * @return type
     */
    public static function sum($con = [], $field = '') {
        if (static::hasField('app_id')) {
            $con[] = ['app_id', '=', session(SESSION_APP_ID)];
        }
        //字段加索引
        static::condAddColumnIndex($con);

        return static::where($con)->sum($field);
    }

    /**
     * 
     * @param type $master  是否从主库获取
     * @return type
     */
    public function commGet() {
        if (!$this->uuid) {
            return [];
        }

        $con    = [];
        $con[]  = ['id','=',$this->uuid];
        if (static::hasField('is_delete')) {
            $con[] = ['is_delete', '=', 0];
        }

        return static::conFind($con);
    }

    /*     * *** 20230728清除数据表全量缓存 ****** */

    /**
     * 
     * @param type $master   $master  是否从主库获取
     * @return type
     */
    public function get($master = false) {
        // 20251023
        $tableName = $this->getTable();
        if(DbOperate::isGlobalDelete($tableName, $this->uuid)){
            return null;
        }
        // 2022-11-20:增加静态数据提取方法
        $source = '';
        if (!$this->uuData && method_exists(static::class, 'staticGet')) {
            $this->uuData = $this->staticGet();
            $source = 'staticGet';
        }

        if (!$this->uuData && !$this->hasUuDataQuery) {
            if (property_exists(static::class, 'getCache') && static::$getCache) {
                // 有缓存的
                // $tableName = static::getTable();
                // $cacheKey = 'mainModelGet_' . $tableName . '-' . $this->uuid;
                $cacheKey = $this->cacheGetKey();
                $this->uuData = Cachex::funcGet($cacheKey, function () use ($master) {
                            return $this->commGet($master);
                        });
                $source = 'getCache';
            } else {
                //没有缓存的
                $this->uuData = $this->commGet($master);
                $source = 'commGet';
            }
            //20220617:增加已查询判断，查空可以不用重复查
            $this->hasUuDataQuery = true;
        }

        // 20230727 ??? 
        if (is_object($this->uuData)) {
            $this->uuData = $this->uuData->toArray();
        }
        
        if($this->uuData){
            $this->uuData['SOURCE'] = $source;
        }

        return $this->uuData;
    }
    
    /**
     * 带缓存get
     * @return type
     */
    public function getCache() {
        $key = static::getTable().'_getCache'.$this->uuid;
        return Cache::funcGet($key, function() {
                    return $this->get();
                });
    }

    /**
     * 20230516:仅从缓存中提取get
     */
    protected function getFromCache() {
        $cacheKey = $this->cacheGetKey();
        return Cache::get($cacheKey);
    }

    /**
     * 批量获取
     */
    public static function batchGet($ids, $keyField = "id", $field = "*") {
        //20220617
        if (!$ids) {
            return [];
        }
        // 20220617只有一条的，通过get取（存内存性能得到提升）
        if (count($ids) == 1 && $ids[0]) {
            $info = static::inst($ids[0])->get();
            $infoArr = is_object($info) ? $info->toArray() : $info;
            return [$ids[0] => $infoArr];
        } else {
            $con[] = ['id', 'in', $ids];
            $lists = static::where($con)->field($field)->select();
            $listsArr = $lists->toArray();
            $listsArrObj = Arrays2d::fieldSetKey($listsArr, $keyField);
            foreach ($listsArrObj as $k => $v) {
                static::inst($k)->setUuData($v, true);  //强制写入
            }
            return $listsArrObj;
        }
    }




    /**
     * 修改数据时，同步调整实例内的数据
     * @useFul 1
     * @param type $newData
     * @param type $force       数据不存在时，是否强制写入（用于从其他渠道获取的数据，直接赋值，不走get方法）
     * @return type
     */
    public function setUuData($newData, $force = false) {
        //强制写入模式，直接赋值
        if ($force) {
            $this->uuData = $newData;
        } else if ($this->uuData) {
            foreach ($newData as $key => $value) {
                $this->uuData[$key] = $value;
            }
        }
        return $this->uuData;
    }

    /**
     * 【弃】逐步废弃：20220606
     * @param type $item
     * @param type $id
     * @return boolean
     */
    protected static function commExtraDetail(&$item, $id) {
        if (!$item) {
            return false;
        }
        return $item;
    }

    /**
     * 2023-01-08：删除公共详情的缓存
     * @param type $ids
     */
    public static function clearCommExtraDetailsCache($ids) {
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        foreach ($ids as $id) {
            $cacheKey = static::commExtraDetailsCacheKey($id);
            Cache::rm($cacheKey);
        }
    }

    /**
     * 2023-01-08：获取数据缓存key
     */
    protected static function commExtraDetailsCacheKey($id) {
        $tableName = static::getTable();
        $baseCacheKey = $tableName . 'commExtraDetails';
        return $baseCacheKey . $id;
    }

    /**
     * 2023-01-08:带缓存查询详情数据
     */
    protected static function commExtraDetailsWithCache($ids, $func = null, $expire = 0) {
        //数组返回多个，非数组返回一个
        $isMulti = is_array($ids);
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        // ====
        $needDbQuery = false;
        $cacheRes = [];
        // 先从缓存数据中提取；
        foreach ($ids as $id) {
            $cacheKey = static::commExtraDetailsCacheKey($id);
            $cacheInfo = Cache::get($cacheKey);
            if (!$cacheInfo) {
                $needDbQuery = true;
            }
            $cacheRes[] = $cacheInfo;
        }
        // 进行数据库查询
        if ($needDbQuery) {
            $lists = static::commExtraDetails($ids, $func);
            foreach ($lists as $v) {
                $cacheKey = static::commExtraDetailsCacheKey($v['id']);
                Cache::set($cacheKey, $v, $expire);
            }

            $cacheRes = $lists;
        }

        return $isMulti ? $cacheRes : $cacheRes[0];
    }

    /**
     * 20220606，闭包公共
     * @param type $ids
     * @param type $func            闭包方法
     * @param type $withUniStatics  是否带关联统计？过渡
     * @return type
     */
    protected static function commExtraDetails($ids, $func = null, $withUniStatics = false) {
        // 20230727??
        $isMulti = is_array($ids);
        if (is_string($ids)) {
            $res = static::inst($ids)->get();
            $ids = [$ids];
            //20230728
            $listsRaw = [$res];
            // return is_object($res) ? $res->toArray() : $res;
        } else {
            //数组返回多个，非数组返回一个
            if (!is_array($ids)) {
                $ids = [$ids];
            }
            //20220619:优化性能
            if (!$ids) {
                return [];
            }
            $con[] = ['id', 'in', $ids];
            //20220706:增加数据隔离
            if (static::hasField('company_id')) {
                $con[] = ['company_id', 'in', session(SESSION_COMPANY_ID)];
            }
            // $listsRaw = static::selectX($con);      
            if (method_exists(static::class, 'staticConList')) {
                $listsRaw = static::staticConList($con);
            } else {
                $listsRaw = static::selectX($con);
            }
            // 20221104:增？？写入内存
            foreach ($listsRaw as &$dataItem) {
                static::inst($dataItem['id'])->setUuData($dataItem, true);  //强制写入
                // 20230516：增加写入缓存
                if (property_exists(static::class, 'getCache') && static::$getCache) {
                    // 有缓存的
                    // $tableName = static::getTable();
                    // $cacheKey = 'mainModelGet_' . $tableName . '-' . $this->uuid;
                    $cacheKey = static::inst($dataItem['id'])->cacheGetKey();
                    static::inst($dataItem['id'])->hasUuDataQuery = true;
                    Cachex::setVal($cacheKey, $dataItem);
                }
            }
        }
        // 加上一些通用的返回字段
        foreach($listsRaw as &$ve){
            $ve['dbSource'] = static::inst()->dbSource();
        }

        // 20220919:返回结果按原顺序输出
        $listsObj = Arrays2d::fieldSetKey($listsRaw, 'id');
        $listsA = [];
        foreach ($ids as &$id) {
            // 20230516：增加isset判断
            if (isset($listsObj[$id])) {
                $listsA[] = $listsObj[$id];
            }
        }
        // 20230528：添加框架的关联统计
        if ($withUniStatics) {
            $listsA = static::listAddUniStatics($listsA);
        }
        // 2022-12-14:【公共的配置式拼接统计数据】
        // 20250305：方法停用
        // $lists = SystemColumnListForeign::listAddStatics(static::getTable(), $listsA);
        //自定义方法：
        // $listsNew = $lists ? ($func ? $func($lists) : $lists) : [];
        $listsNew = $listsA ? ($func ? $func($listsA) : $listsA) : [];

        return $isMulti ? $listsNew : ($listsNew ? $listsNew[0] :[]);
    }

    /**
     * 20230528：列表添加框架的关联统计
     */
    protected static function listAddUniStatics($lists) {
        if (!$lists || !method_exists(static::class, 'objAttrConfList')) {
            return $lists;
        }

        $ids = $lists ? array_column($lists, 'id') : [];
        //【1】批量查询属性列表
        $resList = static::objAttrConfListInList();
        foreach ($resList as $key => $val) {
            // 20250607:增加表存在性判断
            if(!$val['class']::hasTable()){
                continue;
            }
            if(!$val['class']::hasField($val['keyField'])){
                throw new Exception($val['class'].'没有'.$val['keyField'].'字段,请检查代码关联字段配置');
            }
            // 20230608:
            static::objAttrsListBatch($key, $ids);
        }
        //【2】批量查询统计数据【20230608】
        $resStatics = static::objAttrConfListInStatics();
        $statics = [];
        foreach ($resStatics as $k => $v) {
            // 20231026:增加判断 uniField
            if (!$v['inList'] || $v['uniField'] != 'id') {
                $uniField = Arrays::value($v, 'uniField') ?: 'id';
                $statics[$k] = $v['class']::groupBatchCount($v['keyField'], array_column($lists, $uniField));
            }
        }
        //【3】批量查询存在数据【20230608】
        // 注意：这个是纯数组的，跟上面的不一样，（上面的需要过渡优化成数组）
        $resExist = static::objAttrConfListInExist();
        // dump($resExist);
        $exists = [];
        foreach ($resExist as $v) {
            $uniField = Arrays::value($v, 'uniField') ?: 'id';
            $exists[$v['existField']] = $v['baseClass']::groupBatchCount($uniField, array_column($lists, $v['keyField']));
        }
        // Debug::dump('111');
        //【最终】拼接属性列表
        foreach ($lists as &$v) {
            // $key即objAttrs的key
            // 【统计子项数量】
            // 20250708是否有关联数据（一般用于控制删除）
            $v['hasUniCount'] = 0; 
            foreach ($resStatics as $key => $val) {
                // 20250607
                if(!$val['class']::hasTable()){
                    continue;
                }
                // 20241127:增加countField:多个字段关联同一表
                $vKey = Arrays::value($val, 'countField') ? : 'uni' . ucfirst($key) . 'Count';
                if ($val['inList'] && $val['uniField'] == 'id') {
                    $v[$vKey] = static::inst($v['id'])->objAttrsCount($key);
                } else {
                    // 20230608:
                    $staticsData = $statics[$key];
                    // 20230902:改为联动字段
                    $uniField = Arrays::value($val, 'uniField') ?: 'id';
                    $v[$vKey] = Arrays::value($staticsData, $v[$uniField]);
                }
                // 20250708:增加是否有关联数据
                if($v[$vKey] >0){
                    $v['hasUniCount'] = 1;
                }
            }
            // 【存在否】
            foreach ($resExist as &$vv) {
                $fieldName = $vv['existField'];
                $existStaticsArr = Arrays::value($exists, $fieldName, []);
                $value = Arrays::value($v, $vv['keyField'], '');
                $v[$fieldName] = Arrays::value($existStaticsArr, $value, 0);
            }
        }

        return $lists;
    }

    /**
     * 【弃】额外信息获取
     * @param type $item
     * @param type $id
     * @return type
     */
    public static function extraDetail(&$item, $id) {
        return static::commExtraDetail($item, $id);
    }

    /**
     * 公共详情
     * @param type $cache
     * @return type
     */
    protected function commInfo() {
        //额外添加详情信息：固定为extraDetails方法
        if (method_exists(static::class, 'extraDetails')) {
            $info = static::extraDetails($this->uuid);
        } else {
            $infoRaw = $this->get();
            // 2022-11-20???
            if (is_object($infoRaw)) {
                $info = $infoRaw ? $infoRaw->toArray() : [];
            } else {
                $info = $infoRaw ?: [];
            }
        }
        /** 20220514；增加动态枚举数据返回 ************ */
        $info = $this->pushDynDataList($info);

        return $info;
    }
    /**
     * 20240802
     * @param type $info
     * @return type
     */
    protected function pushDynDataList(&$info) {
        $columnId = SystemColumn::tableNameGetId(static::getRawTable());
        $dynFields = SystemColumnList::columnTypeFields($columnId, 'dynenum');
        // dump($dynFields);
        $dynDatas = [];
        foreach ($dynFields as $key) {
            $dynDatas[$key] = Arrays::value($info, $key);    // array_unique(array_column($info,$key));
        }
        // dump($info);
        // 固定dynDataList
        if ($info) {
            // $info['dynDataList'] = SystemColumnList::sDynDataList($columnId, $dynDatas);
        }

        return $info;
    }
    /**
     * 详情
     * @param type $cache
     * @return type
     */
    public function info() {
        //如果cache小于-1，表示外部没有传cache,取配置的cache值
        // $cache = $cache < 0 ? static::defaultCacheTime() : $cache;
        return $this->commInfo();
    }



    /**
     * 末条记录id
     * @return type
     */
    public static function lastId() {
        return static::order('id desc')->value('id');
    }

    /*
     * 上一次的值
     */

    public static function lastVal($field, $con = []) {
        return static::where($con)->order('id desc')->value($field);
    }

    /**
     * 	公司是否有记录（适用于SARRS）
     */
    public static function companyHasLog($companyId, $con) {
        $con[] = ['company_id', '=', $companyId];
        return static::find($con);
    }

    /**
     * 判断表中某个字段是否有值
     * @param type $field
     * @param type $value
     */
    public static function fieldHasValue($field, $value) {
        $con[] = [$field, '=', $value];
        return static::where($con)->count();
    }

    /**
     * 20220620：是否有前序数据（单条）
     * 前序订单，前序账单
     * @param type $fieldName
     */
    public function getPreData($fieldName) {
        $info = $this->get();
        $preId = Arrays::value($info, $fieldName);
        if (!$preId) {
            return false;
        }
        return static::inst($preId)->get();
    }

    /**
     * 20231019 
     * @return type
     */
    public static function getTimeField() {
        return property_exists(static::class, 'timeField') ? static::$timeField : '';
    }

    /**
     * 20240402:固定字段
     * @return type
     */
    public static function getFixedFields() {
        return property_exists(static::class, 'fixedField') ? static::$fixedField : [];
    }

    /**
     * 20240402:源头字段
     * 当源头字段发生变化时，应通知上下文进行更新动作 
     * @return type
     */
    public static function getSourceFields() {
        return property_exists(static::class, 'sourceField') ? static::$sourceField : [];
    }

    /**
     * 20220620 获取后续数据清单
     * 后序订单，后序账单……
     * 20220622 未入库的取不到……
     */
    public function getAfterDataArr($fieldName) {
        global $glSaveData;
        $tableName = static::getTable();

        $con[] = [$fieldName, '=', $this->uuid];
        //提取未入库数据
        $noSaveArrs = array_values(Arrays::value($glSaveData, $tableName, []));
        $idsNoSave = array_column(Arrays2d::listFilter($noSaveArrs, $con), 'id');
        //提取已入库数据
        if (static::hasField('is_delete')) {
            $con[] = ['is_delete', '=', 0];
        }
        // 2022-11-20: 增加cache(1)缓存
        $idsSaved = static::where($con)->cache(1)->column('id');
        //合并未入库和已入库数据
        $ids = array_merge($idsNoSave, $idsSaved);
        $info = $this->get();
        if (Arrays::value($info, 'afterIds', [])) {
            $ids = array_merge($ids, $info['afterIds']);
        }
        $dataArr = [];
        foreach ($ids as $id) {
            $dataArr[$id] = static::inst($id)->get();
        }
        return $dataArr;
    }

    /*     * ********【20230531】注入触发器 ********************************** */

    /**
     * 20230518：提取配置数组
     * 
      protected static $trigger = [
      'afterOrderPay'=>[
      'dealMethod'    =>'customer_id',
      'dealClass'     =>'xjryanse\dev\service\DevProjectExtService'
      ]
      ];
     * 
     */
    public static function confArrTrigger() {
        $lists = property_exists(static::class, 'trigger') ? static::$trigger : [];
        $resArr = [];
        foreach ($lists as $k => $v) {
            $tmp = $v;
            $tmp['class'] = static::class;
            $tmp['property'] = $k;

            $resArr[] = $tmp;
        }
        return $resArr;
    }

    /**
     * 20231113:提取关联的映射字段
     */
    public static function uniReflectFields() {
        if (!property_exists(static::class, 'uniFields')) {
            return [];
        }
        $fields = static::$uniFields;
        $reflectFields = [];
        foreach ($fields as $v) {
            // 20231113:映射字段
            /*
              'reflect_field' => [
              // hasStatement 映射到表finance_statement_order的has_statement
              'hasStatement'  => 'has_statement',
              'hasSettle'     => 'has_settle'
              ],
             */
            $reflects = Arrays::value($v, 'reflect_field') ?: [];

            $reflectFields = array_merge($reflectFields, array_keys($reflects));
        }
        return $reflectFields;
    }

    /**
     * 20220711:用于跨系统迁移数据
     * @param type $sourceId
     * @return boolean
     */
    public static function sourceIdToId($sourceId) {
        if (!$sourceId && $sourceId !== 0) {
            return false;
        }
        $con[] = ['source_id', '=', $sourceId];
        if (static::hasField('company_id')) {
            $con[] = ['company_id', '=', session(SESSION_COMPANY_ID)];
        }
        return static::where($con)->value('id');
    }

    /**
     * 20221116,从逗号分隔中查询数据
     */
    public static function sourceIdToIdSet($sourceId) {
        if (!$sourceId && $sourceId !== 0) {
            return false;
        }
        if (static::hasField('company_id')) {
            $con[] = ['company_id', '=', session(SESSION_COMPANY_ID)];
        }
        return static::where($con)->whereRaw("FIND_IN_SET('" . $sourceId . "', source_id)")->value('id');
    }

    /**
     * 校验是否有来源数据。
     * @param type $sourceId
     * @return boolean
     */
    public static function hasSource($sourceId) {
        if (!$sourceId || !static::hasField('source_id')) {
            return false;
        }
        $con[] = ['source_id', '=', $sourceId];
        if (static::hasField('company_id')) {
            $con[] = ['company_id', '=', session(SESSION_COMPANY_ID)];
        }
        if (static::hasField('is_delete')) {
            $con[] = ['is_delete', '=', 0];
        }
        return static::where($con)->count();
    }




    /*     * *
     * 一个列表，提取动态数据
     * @param array $arr        列表
     * @param type $arrField    列表的key
     * @param type $columnField 本表的key
     * @param type $keyField    本表关联，默认id
     */

    public static function arrDynenum(array $arr, $arrField, $columnField, $keyField = 'id') {
        $ids = Arrays2d::uniqueColumn($arr, $arrField);
        $con[] = [$keyField, 'in', $ids];
        return static::where($con)->column($columnField, $keyField);
    }

    /**
     * 数据导出逻辑：
     * 方法列表 + 写入模板key
     */

    /**
     * 导出数据到模板
     */
    public static function exportListToTpl($param) {
        DataCheck::must($param, ['listMethod', 'generateTplKey']);
        // 列表方法
        $listMethod = Arrays::value($param, 'listMethod');
        // excel模板key
        $templateKey = Arrays::value($param, 'generateTplKey');
        // 步骤1：提取列表数据
        $lists = static::$listMethod($param);
        // 步骤2：拼接到模板
        $templateId = GenerateTemplate::keyToId($templateKey);

        if (!$templateId) {
            throw new Exception('模板不存在:' . $templateKey);
        }
        // 20231229
        foreach ($lists as $k => &$v) {
            $v['i'] = $k + 1;
        }
        // 步骤3：返回前台下载
        $resp = GenerateTemplateLog::export($templateId, $lists);

        $res['url'] = $resp['file_path'];
        $res['fileName'] = date('YmdHis') . '.xlsx';

        return $res;
    }

    /**
     * 数据，公共取id，无时新增
     * 20231230
     * @param type $data
     * @param type $ifEmptyId   用于对id有要求的情况，比如分库分表
     * @param type $sepKeys     20250818:分表key
     * @return type
     */
    public static function commGetIdEG($data, $ifEmptyId = '',$sepKeys = []) {
        $id = static::commGetId($data, $sepKeys);
        if (!$id) {
            // 20241206
            $data['id'] = $ifEmptyId ?: SnowFlake::generateParticle();
            $id = static::saveGetIdRam($data);
        }
        // Debug::dump(static::ramGlobalSaveData());
        return $id;
    }

    /**
     * 数据，公共取id
     * @createTime 2023-12-30 13:52:00
     * @param type $data
     * @param type $sepKeys 分表key(20250818)
     * @return type
     */
    public static function commGetId($data, $sepKeys = []) {
        $con = [];
        foreach ($data as $k => $v) {
            if($v === null){
                // 20250914:增加null
                $con[] = [$k, 'is', 'null'];
            } else {
                $con[] = [$k, '=', $v];
            }
        }

        $info   = static::inst()->reset()->where($con, $sepKeys)->cache(1)->find();
        $id     = Arrays::value($info, 'id');
        if(!$id){
            // 20240601:增加ram获取
            $id = static::ramValue('id',$con);
        }
        return $id;
    }

    /**
     * 20250416
     * @return type
     */
    public static function lastSort($field,$value, $con = []){
        $con[]  = [$field,'=',$value];
        $info = self::inst()->where($con)->order('id desc')->find();
        return Arrays::value($info, 'sort') ?:0;
    }
}

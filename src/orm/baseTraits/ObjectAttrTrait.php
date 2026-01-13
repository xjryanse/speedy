<?php

namespace xjryanse\speedy\orm\baseTraits;

use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\logic\Arrays2d;
use xjryanse\speedy\orm\DbOperate;
use Exception;

/**
 * 对象属性复用
 * (配置式数据表)
 */
trait ObjectAttrTrait {

    public static $attrTimeCount = 0;   //末个节点执行次数

    // 定义对象的属性
    protected $objAttrs = [];
    // 定义对象是否查询过的属性
    protected $hasObjAttrQuery = [];

    /**
     * 20230608
     * @return type
     */
    public static function objAttrConfListInList() {
        $lists = static::objAttrConfList();
        $arr = [];
        foreach ($lists as $k => $v) {
            if ($v['inList']) {
                $arr[$k] = $v;
            }
        }
        return $arr;
    }

    /**
     * 20230608
     * @return type
     */
    public static function objAttrConfListInStatics() {
        $lists = static::objAttrConfList();
        $arr = [];
        foreach ($lists as $k => $v) {
            if ($v['inStatics']) {
                $arr[$k] = $v;
            }
        }
        return $arr;
    }

    /**
     * 20230608
     * @return type
     */
    public static function objAttrConfListInExist() {
        $lists = static::objAttrConfListUniPre();
        $arr = [];
        foreach ($lists as $v) {
            if ($v['inExist']) {
                $arr[] = $v;
            }
        }
        return $arr;
    }

    /**
     * 20230528:注入联动（默认后向）
     */
    protected static function objAttrConfList() {
        // 20230603:需要加反斜杠？？
        $className = '\\' . static::class;
        $con[] = ['baseClass', '=', $className];
        $lists = DbOperate::uniAttrConfArr($con);
        $objAttrConf = [];
        foreach ($lists as $v) {
            $objAttrConf[$v['property']] = static::objConfDataDeal($v);
        }

        return $objAttrConf;
    }

    /**
     * 20230608:前向
     * @return type
     */
    protected static function objAttrConfListUniPre() {
        // 20230603:需要加反斜杠？？
        $className = '\\' . static::class;
        $con[] = ['class', '=', $className];
        $lists = DbOperate::uniAttrConfArr($con);
        // 20230608：TODO；前面的应该全改成这种
        return $lists;
    }

    /**
     * 
     * @useFul 1
     * @param type $v   DbOperate::uniAttrConfArr()，查询的单条数组列表;
     * @return type
     */
    private static function objConfDataDeal($v) {
        return [
            'class'     => $v['class'],
            'keyField'  => $v['keyField'],
            'master'    => true,
            'uniField'  => Arrays::value($v, 'uniField', ''),
            'inList'    => Arrays::value($v, 'inList', true),
            'inStatics' => Arrays::value($v, 'inStatics', true),
            'inExist'   => Arrays::value($v, 'inExist', true),
            // 20230608:字段存在，显示值
            'existField' => Arrays::value($v, 'existField', ''),
            // 20241130
            'countField' => Arrays::value($v, 'countField'),
        ];
    }

    // 20221024:批量获取属性列表
    public static function objAttrsListBatch($key, $ids) {
        if (!$ids) {
            return [];
        }
        if (!is_array($ids)) {
            $ids = [$ids];
        }
        // 20230729??优化性能，只有一个
        if (count($ids) == 1) {
            $id = $ids[0];
            return static::inst($id)->objAttrsList($key);
        }
        // 取配置
        $config = Arrays::value(static::objAttrConfList(), $key);
        if (!$config) {
            throw new Exception('未配置' . $key . '的对象属性信息，请联系开发解决1');
        }
        $class = Arrays::value($config, 'class');
        $keyField = Arrays::value($config, 'keyField');
        $master = Arrays::value($config, 'master', false);

        //查数据
        $con[] = [$keyField, 'in', $ids];
        if ($class::hasField('is_delete')) {
            $con[] = ['is_delete', '=', 0];
        }
        //Debug::debug('objAttrsList_'.$key.'的条件', $con);
        $lists = $class::listSetUudata($con, $master);
        $listsArr = $lists ? (is_array($lists) ? $lists : $lists->toArray()) : [];

        foreach ($ids as $id) {
            $inst = static::inst($id);
            $conEle = [];
            $conEle[] = [$keyField, '=', $id];
            $inst->objAttrs[$key] = Arrays2d::listFilter($listsArr, $conEle);
            //已经有查过了就不再查了，即使为空
            $inst->hasObjAttrQuery[$key] = true;
        }
        // 批量获取属性
        return $listsArr;
    }

    /**
     * 20240228：判断是否有数据
     * @useFul 1
     * @param type $key
     */
    public function objAttrsHasData($key) {
        return property_exists($this, 'objAttrs') ? Arrays::value($this->objAttrs, $key) : false;
    }

    /**
     * 20240313
     * @useFul 1
     * @param type $key
     * @return type
     */
    public function objAttrsHasQuery($key) {
        return property_exists($this, 'hasObjAttrQuery') ? Arrays::value($this->hasObjAttrQuery, $key) : false;
    }

    /**
     * 20240411:封装了过滤方法
     * @param type $key
     * @param type $con
     */
    public function objAttrsListFilter($key, $con = []) {
        $lists = $this->objAttrsList($key);
        return Arrays2d::listFilter($lists, $con);
    }

    /**
     * 内存属性是否存在
     * @param type $key
     */
    public static function objAttrExist($key){
        return Arrays::value(static::objAttrConfList(), $key) ? true : false;
    }
    /**
     * 20241214
     * @param type $key
     * @return type
     * @throws Exception
     */
    public function objAttrsKeyField($key) {
        $config = Arrays::value(static::objAttrConfList(), $key);
        if (!$config) {
            $table = static::getRawTable();
            throw new Exception($table.'未配置' . $key . '属性，请联系开发解决2');
        }
        
        $keyField = Arrays::value($config, 'keyField');
        return $keyField;
    }
    /**
     * 对象属性列表
     * @useFul 1
     * @param type $key
     * @return type
     * @throws Exception
     */
    public function objAttrsList($key,$con = []) {
        // 20230730
        $objAttrs = property_exists($this, 'objAttrs') ? $this->objAttrs : [];
        $hasObjAttrQuery = property_exists($this, 'hasObjAttrQuery') ? $this->hasObjAttrQuery : [];
        // 20240224 
        if (!Arrays::value($objAttrs, $key) && !Arrays::value($hasObjAttrQuery, $key)) {
            // 取配置
            $config = Arrays::value(static::objAttrConfList(), $key);
            if (!$config) {
                $table = static::getRawTable();
                throw new Exception($table.'未配置' . $key . '属性，请联系开发解决3');
            }

            $class = Arrays::value($config, 'class');
            $keyField = Arrays::value($config, 'keyField');
            $master = Arrays::value($config, 'master', false);
            //查数据
            $con[] = [$keyField, '=', $this->uuid];
            // 20231020：增加分表逻辑（体检板块）
            if ($class::hasField('is_delete')) {
                $con[] = ['is_delete', '=', 0];
            }
            // 20250818避免重复条件
            $con    = array_unique($con);
            $lists  = $class::listSetUudata($con, $master);
            $listsArr = $lists ? (is_array($lists) ? $lists : $lists->toArray()) : [];

            // 20241215:处理被更新的异常数据
            foreach($listsArr as $k=>$v){
                // 20241215：验证key是否依然匹配:不匹配说明被更新了，应该把本项移除
                if(Arrays::value($v, $keyField) != $this->uuid){
                    unset($listsArr[$k]);
                }
            }
            //Debug::debug('objAttrsList_'.$key.'的$lists', $lists);
            $this->objAttrs[$key] = array_values($listsArr);
            //已经有查过了就不再查了，即使为空
            $this->hasObjAttrQuery[$key] = true;
        }
        
        
        return $this->objAttrs[$key];
    }

    /**
     * 设定对象属性
     * @param type $key
     * @param type $data
     */
    public function objAttrsSet($key, $data) {
        $this->objAttrs[$key] = $data;
        $this->hasObjAttrQuery[$key] = true;
    }

    /**
     * 20220619:删除对象属性
     * @param type $key
     * @param type $id  主键
     */
    public function objAttrsUnSet($key, $id) {
        // 20230730:增加property_exists判断
        if ((!property_exists($this, 'objAttrs') || is_null($this->objAttrs[$key])) && (!property_exists($this, 'hasObjAttrQuery') || !Arrays::value($this->hasObjAttrQuery, $key))) {
            $this->objAttrsList($key);
        }

        foreach ($this->objAttrs[$key] as $k => $v) {
            if ($v['id'] == $id) {
                unset($this->objAttrs[$key][$k]);
            }
        }
    }

    /**
     * 新增对象属性;用于数据库中添加后，内存中同步添加
     * @useFul 1
     * @param type $key
     * @param type $data
     */
    public function objAttrsPush($key, $data) {
        $hasObjAttrQuery = property_exists($this, 'hasObjAttrQuery') ? $this->hasObjAttrQuery : [];
        // 20230730：似乎可以优化？？？
        if ((!property_exists($this, 'objAttrs') || !isset($this->objAttrs[$key]) || is_null($this->objAttrs[$key])) && !Arrays::value($hasObjAttrQuery, $key)) {
            $this->objAttrsList($key);
        }

        if (Arrays::value($this->hasObjAttrQuery, $key)) {
            //有节点，往节点末尾追加
            $this->objAttrs[$key][] = $data;
        }
    }

    /**
     * 对象属性更新
     * @param type $key
     * @param type $dataId
     * @param type $data
     */
    public function objAttrsUpdate($key, $dataId, $data) {
        $objAttrs = property_exists($this, 'objAttrs') ? $this->objAttrs : [];
        $hasObjAttrQuery = property_exists($this, 'hasObjAttrQuery') ? $this->hasObjAttrQuery : [];
        //20230801：没有获取时，先获取一遍
        if ((!$objAttrs || !isset($objAttrs[$key]) || is_null($objAttrs[$key])) && !Arrays::value($hasObjAttrQuery, $key)) {
            $this->objAttrsList($key);
        }
        //有节点，往节点末尾追加
        $hasMatch = false;
        if (Arrays::value($this->objAttrs, $key)) {
            // 20241215
            $keyField = $this->objAttrsKeyField($key);
            // 遍历内存已有属性值进行更新
            foreach ($this->objAttrs[$key] as $k=>&$v) {
                if ($v['id'] == $dataId) {
                    $hasMatch = true;
                    //20220622:TODO;
                    if (is_object($v)) {
                        $v = $v->toArray();
                    }
                    if (is_object($data)) {
                        $data = $data->toArray();
                    }
                    $v = array_merge($v, $data);
                }
            }
        }

        // 2022-12-09:如果原先没有，则追加
        if (!$hasMatch) {
            $this->objAttrs[$key][] = $data;
        }
    }

    /*
     * 属性记录数
     */
    public function objAttrsCount($key, $con = []) {
        $listRaw = $this->objAttrsList($key);
        $list = Arrays2d::listFilter($listRaw, $con);
        return count($list);
    }

    /**
     * 属性指定字段求和
     * @param type $key
     * @param type $sumField
     * @return type
     */
    public function objAttrsFieldSum($key, $sumField, $con = []) {
        $listRaw = $this->objAttrsList($key);
        $list = Arrays2d::listFilter($listRaw, $con);
        return Arrays2d::sum($list, $sumField);
    }
}

<?php

namespace xjryanse\speedy\traits;

/**
 * 有限多例复用
 */
trait InstMultiTrait {

    protected static $instances = [];
    protected $uuid;

    /**
     * @useFul 1
     */
    protected function __clone() {

    }

    /**
     * 兼容原有代码，正常使用不应直接实例化
     * @param type $uuid
     * @useFul 1
     */
    public function __construct($uuid = 0) {
        $this->uuid = $uuid;
    }

    /**
     * 有限多例
     * 数据混乱.为每个子类单独存储实例：可以在 $instances 数组中以类名为键，将不同子类的实例分开存储。
     * @useFul 1
     * @param type $uuid
     * @return type
     */
    public static function inst($uuid = 0) {
        $classMd5 = md5(static::class);
        if (!isset(static::$instances[$classMd5][$uuid]) || !static::$instances[$classMd5][$uuid]) {
                static::$instances[$classMd5][$uuid] = new static($uuid);
            // 20250214:增加初始化方法，方便一些用户数据处理
            // 20250912:外部移到内部
            if(method_exists(static::class, 'instInit')){
                static::$instances[$classMd5][$uuid]->instInit();
            }
        }
        return static::$instances[$classMd5][$uuid];
    }
    
    // 判断是否已实例化
    public static function hasInst($uuid) {
        $classMd5 = md5(static::class);
        return static::$instances[$classMd5] && static::$instances[$classMd5][$uuid];
    }
}

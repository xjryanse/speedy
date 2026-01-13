<?php

namespace xjryanse\speedy\orm\baseTraits;

use xjryanse\speedy\logic\Arrays2d;
/**
 * 数据处理复用
 * 20230805
 */
trait DataDealTrait {
    /**
     * 数据带获取器进行转换
     * 
     */
    public static function dataDealAttr(array $data){
        /* 图片字段提取 */
        if (property_exists(static::class, 'picFields')) {
            $picFields = static::$picFields;
            $data = Arrays2d::picFieldCov($data, $picFields);
        }
        /**多图**/
        if (property_exists(static::class, 'multiPicFields')) {
            $multiPicFields = static::$multiPicFields;
            $data = Arrays2d::multiPicFieldCov($data, $multiPicFields);
        }
        /* 2022-12-18 混合图片的字段提取，如:配置表 */
        if (property_exists(static::class, 'mixPicFields')) {
            $mixPicFields = static::$mixPicFields;
            $data = Arrays2d::mixPicFieldCov($data, $mixPicFields);
        }
        return $data;
    }
    
    
    
    
}

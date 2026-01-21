<?php

namespace xjryanse\speedy\orm;

use xjryanse\speedy\core\Db;

/**
 * 数据库连接逻辑
 */
class DbCnn {
    /**
     * 
     */
    public static function idInfo($id){
        $sql = "select * from w_db_cnn where id = '".$id."'";
        $info = Db::inst(0)->find($sql);
        return $info;
    }

    
}

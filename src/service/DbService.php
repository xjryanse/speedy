<?php
namespace xjryanse\speedy\service;

use xjryanse\speedy\core\db\DbFmp;
use orm\db\DbTableDatacount;
use orm\db\DbCnn;
use xjryanse\speedy\orm\DbOperate;
use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\logic\Arrays2d;
// use app\entry\sdk\EntrySdk;
use xjryanse\servicesdk\entry\EntrySdk;
use Exception;
/**
 * 2025年12月28日；21点59分
 */
class DbService {
    
    /**
     * 获取数据库连接
     * @param type $dbSource
     * @return type
     * @throws Exception
     */
    public static function dbConf($dbSource){
        $md5RPH = md5(ROOT_PATH);
        // 为了兼容cli下使用，增加md
        $host = $_SERVER['SERVER_NAME'] ?: $md5RPH;
        $info = EntrySdk::hostBindInfo($host);
        if(!$info){
            
            $info = EntrySdk::hostBindInfo($md5RPH);
            if(!$info){
                throw new Exception('未找到数据库配置:'.$md5RPH.'，路径'.ROOT_PATH);
            }
        }
        // $keys = ['hostname','database','username','password','hostport','charset'];
        return $info[$dbSource];
    }
    
    /**
     * 20250226：获取数据库连接id
     * 20251228:微服务改造
     * @param type $dbSource
     * @return type
     */
    public static function dbId($dbSource){
        $host = $_SERVER['SERVER_NAME'];
        $info = EntrySdk::hostBindInfo($host);

        if(!$info){
            throw new Exception('未找到数据库配置:'.$host);
        }
        if(!Arrays::value($info, $dbSource)){
            throw new Exception($host.'未配置数据库:'.$dbSource);
        }
        return $info[$dbSource];
    }
    /**
     * 20250226：客户端显示数据库连接信息（脱敏）
     */
    public static function dbConfArrFr(){
        $cnnList = DbCnn::staticConList();
        $keys   = ['id','cnn_name'];
        $cnnObj = Arrays2d::fieldSetKey(Arrays2d::getByKeys($cnnList, $keys), 'id');
        
        $data['dbSys']  = $cnnObj[self::dbId('dbSys')];
        $data['dbBusi'] = $cnnObj[self::dbId('dbBusi')];
        $data['dbLog']  = $cnnObj[self::dbId('dbLog')];
        return $data;
    }
    
    
    protected $dbFpm;
    
    public function __construct(DbFmp $cnn) {
        $this->dbFpm = $cnn;
    }
    /**
     * 20250205：数据表同步
     */
    public function tableSync($sData = []){
        $tables = $this->allTables();
        foreach($tables as $v){
            $sData = array_merge($sData,$v);
            DbTableDatacount::inst()->saveRam($sData);
        }
        DbOperate::dealGlobal();
        return count($tables);
    }
    /**
     * 20250908
     * @return type
     */
    public function allTables(){
        $database = $this->dbFpm->getConfig('database');
        $sql = "SELECT
                TABLE_NAME as `table`
            FROM
                information_schema.`TABLES` 
            WHERE
                table_schema = '".$database."'";

        $tables = $this->dbFpm->query($sql);
        return $tables;
    }
    
    /**
     * 同步统计数据表记录数
     */
    public static function countSync(){
        $count = DbTableDatacount::todoCount();
        echo $count.'条待同步';
        if(!$count){
            return true;
        }
        $list = DbTableDatacount::todoList();
        if(!$list){
            throw new Exception('没有需要同步的数据');
        }
        
        $cnnId  = $list ? $list[0]['cnn_id'] : '';
        $config = DbCnn::inst($cnnId)->get();

        $fpm = new DbFmp();
        $fpm->connect($config);

        foreach($list as $item){
            if($item['cnn_id']!=$cnnId){
                continue;
            }
            $sql = "select count(1) as data_count from ".$item['table'];
            $upData = $fpm->find($sql);
            $upData['sync_time'] = date('Y-m-d H:i:s');
            //更新
            DbTableDatacount::inst($item['id'])->update($upData);
        }
    }
    
    
    
    

}

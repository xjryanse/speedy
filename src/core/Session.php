<?php

namespace xjryanse\speedy\core;

use xjryanse\speedy\facade\Redis;
use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\logic\Strings;
use Exception;

class Session
{
    use \xjryanse\speedy\traits\InstTrait;
    
    protected $sessionid;
    // 过期时间
    private $expire = 3600;
    /*
     * 自建，用于改造适配swoole
     */
    public function setSessionid($sessionid=''){
        if(!$sessionid){
            // 随机生成一个sessionid
            $sessionid = Strings::rand(22);
        }
        $this->sessionid = $sessionid;
    }

    public function getSessionid(){
        return $this->sessionid;
    }
    
    protected function ssInst(){
        $ssInst = Redis::ssInst();
        return $ssInst;
    }
    /**
     * 更新完整的会话数据
     * @param type $data
     */
    protected function setData($data){
        Redis::ssInst()->set($this->sessionid, serialize($data));
    }
    
    protected function getData(){
        $dataStr = Redis::ssInst()->get($this->sessionid);
        if ($dataStr) {
            // 刷新会话过期时间
            Redis::ssInst()->expire($this->sessionid, $this->expire);
        }

        return $dataStr ? unserialize($dataStr) : null;
    }
    
    /**
     * session获取
     * @access public
     * @param  string        $name session名称
     * @param  string|null   $prefix 作用域（前缀）
     * @return mixed
     */
    public function get($name = '', $prefix = null)
    {
        $keyFinal    = $prefix.$name;
        $currentData = $this->getData();
        if(!$currentData){
            return null;
        }
        return Arrays::value($currentData, $keyFinal);
    }
    
    /**
     * session设置
     * @access public
     * @param  string        $name session名称
     * @param  mixed         $value session值
     * @param  string|null   $prefix 作用域（前缀）
     * @return void
     */
    public function set($name, $value, $prefix = null) {
        $keyFinal    = $prefix.$name;
        $currentData = $this->getData();
        if(!$currentData){
            $currentData = [];
        }
        $currentData[$keyFinal] = $value;
        $this->setData($currentData);
        return $value;
    }
    
    public function clear(){
        Redis::ssInst()->del($this->sessionid);
    }
}

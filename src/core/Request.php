<?php
namespace xjryanse\speedy\core;

use speedy\facade\Route;
use speedy\facade\Cache;
use speedy\logic\Arrays;
use speedy\logic\Strings;
use speedy\logic\Url;
use speedy\interfaces\RqParamsInterface;
use Exception;
/**
 * 请求入参
 */
class Request{
    use \speedy\traits\InstTrait;
    
    protected $rqParamInst;
    
    public function setRqParams(RqParamsInterface $rqParamInst){
        $this->rqParamInst = $rqParamInst;
    }

    public function param($key = ''){
        $get    = $this->rqParamInst->get() ? : [];
        $post   = $this->rqParamInst->post() ? : [];
        $param = array_merge($get, $post);
        return $key ? Arrays::value($param, $key) : $param;
    }
    
    public function post($key = ''){
        return $this->rqParamInst->post($key);
    }
    
    /**
     * 20250204:请求的ip地址
     */
    public function ip(){
        return $this->rqParamInst->ip();
    }
    
    public function server($name){
        throw new Exception('server调试中');
//        $server = RqParams::getServer();
//        return Arrays::value($server, $name);
    }
    
    public function host(){
        $port = $this->rqParamInst->port();
        $host = $this->rqParamInst->host();
        // ip地址的才加端口
        if(!in_array($port,[80,443]) && !Strings::hasStr($host, ':') && !Url::isValidDomain($host)){
            return $host.':'.$port;
        } else {
            return $host;
        }
    }
    
    public function schema(){
        throw new Exception('schema调试中');
//        $server = RqParams::getServer();
//        return Arrays::value($server, 'REQUEST_SCHEME');
    }
    
    public function url(){
        return $this->rqParamInst->uri();
    }
    
    public function env(){
        return $this->rqParamInst->env();
    }
    
    public function domain($port = false){
        return $this->rqParamInst->scheme().'://'.$this->rqParamInst->host($port);
    }

    public function header($name){
        return $this->rqParamInst->header($name);
    }
    
    /**
     * 20250311:上传文件
     * @param type $name
     * @return type
     * 
     *  array(5) {
            ["name"] => string(36) "ad1e0305de591adde540e2ea1a2c51a2.jpg"
            ["tmp_name"] => string(28) "/tmp/workerman.upload.KLDvIS"
            ["size"] => int(76036)
            ["error"] => int(0)
            ["type"] => string(10) "image/jpeg"
          }
     */
    public function file($name){
        return $this->rqParamInst->file($name);
//        // 文件的实例
//        return (new File($file['tmp_name']))->setUploadInfo($file);
    }
    
    public function input($key = ''){
        return $this->rqParamInst->input($key);
    }
    /**
     * 20250403：判断是否ajax请求
     */
    public function isAjax(){
        return $this->rqParamInst->isAjax();
    }
    
    /**
     * 通用接口防抖函数
     */
    public function antiRepeat() {
        // 模块名
        $modules    = Route::module();
        // 控制器名称
        $controller = Route::controller();
        // 方法名称
        $method     = Route::action();
        
        $param      = $this->param();
        $md5        = md5(json_encode($param, JSON_UNESCAPED_UNICODE));
        $key = 'AntiRepeat_' . $modules . '.' . $controller . '.' . $method . '.'.$md5;
        // 访问进行中
        if (Cache::get($key)) {
            throw new Exception('操作频繁');
        } else {
            // 3秒不能重复点击
            Cache::set($key,1,3);
        }
    }
}

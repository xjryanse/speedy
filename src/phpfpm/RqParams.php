<?php

namespace xjryanse\speedy\phpfpm;

use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\facade\Session;
use xjryanse\speedy\interfaces\RqParamsInterface;
use Exception;
/**
 * 请求入参
 */
class RqParams implements RqParamsInterface{

    use \xjryanse\speedy\traits\InstTrait;

    // get参数
    protected $get      = [];
    // post参数
    protected $post     = [];
    protected $input    = null;
    // 请求头
    protected $header   = [];
    // public $cookies;
    protected $files    = [];
    // 20250201:server参数
    protected $server   = [];
    // 运行环境：fpm;swoole
    protected $env      = [];
    protected $ip;

    public function setRequest( $request = null ) {
        $params = [];
        $this->server   = $_SERVER;
        foreach ($this->server as $key => $val) {
            if (0 === strpos($key, 'HTTP_')) {
                $key = str_replace('_', '-', strtolower(substr($key, 5)));
                $this->header[$key] = $val;
            }
        }

        $rawData        = file_get_contents('php://input');
        // 20250313
        $this->input    = $rawData;
        $postData       = json_decode($rawData, true);
        // 处理 POST 参数
        if ($postData) {
            $this->post = $postData;
        }
        
        $this->get      = $_GET;
        // 处理 POST 参数
        $this->files    = $_FILES;
        // $this->cookie   = $request->cookie;
        // 处理上传文件
        $this->env = 'phpfpm';
        $this->sessionInit();
        return $params;
    }
    
    public function env() {
        return $this->env;
    }

    public function header($key = '') {
        $header = $this->header;
        return $key ? Arrays::value($header, $key) : $header;
    }
    public function get($key = '') {
        $get = $this->get;
        return $key ? Arrays::value($get, $key) : $get;
    }

    public function post($key = '') {
        $post = $this->post;
        return $key ? Arrays::value($post, $key) : $post;
    }

    public function input() {
        return $this->input;
    }

    public function host() {
        // 20251020:代理的
        if(isset($this->header['x-forwarded-host'])){
            return $this->header['x-forwarded-host'];
        }
        return $this->header['host'];
    }
    
    public function port() {
        return $this->server['SERVER_PORT'];
    }

    public function scheme(): string {
        if(!empty($this->server['ssl_protocol'])){
            return 'https';
        }
        return $this->header('x-forwarded-proto') ? : 'http';
    }

    public function method(): string {
        return 'method调试中';
    }

    public function uri(): string {
        return $this->server['REQUEST_URI'] ? : '';
    }
    
    public function isAjax(){
        $server = $_SERVER;
        $xRequestedWith = Arrays::value($server, 'HTTP_X_REQUESTED_WITH');
        return $xRequestedWith && strtolower($xRequestedWith) == 'xmlhttprequest';
    }
    
    public function ip(): string {
        return $this->header('x-real-ip') ? : $this->header('x-forwarded-for');
    }

    protected function sessionInit() {
        $sessionid = Arrays::value($this->header, 'sessionid');
        // 未传时，将随机生成一个sessionid
        Session::setSessionid($sessionid);
    }
}

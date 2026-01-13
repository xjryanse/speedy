<?php
namespace xjryanse\speedy\interfaces;

interface RqParamsInterface {
    public function setRequest($request = null);
    // 获取请求协议 (http/https)
    public function scheme(): string;
    // 获取请求方法 (GET/POST等)
    public function method(): string;
    // 获取请求URI
    public function uri(): string;
    // 域名
    public function host();
    // 获取请求头
    public function header(string $key = null);
    // 获取GET参数
    public function get(string $key= null);
    // 获取POST参数
    public function post(string $key= null);
    // 标记当前运行环境：fpm；swoole
    public function env();
    
    public function ip();
}

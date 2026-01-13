<?php
namespace xjryanse\speedy\core;

use xjryanse\speedy\logic\Arrays;
use xjryanse\speedy\logic\Strings;
use orm\system\SystemCompany;
use xjryanse\speedy\interfaces\RqParamsInterface;
/**
 * 路由解析
 * 2026年1月13日
 */
class Route{   
    /*******单例********************************/
    use \xjryanse\speedy\traits\InstTrait;
    
    // 公司key
    protected $comKey;
    // 模块
    protected $module;
    // 控制器
    protected $controller;
    // 方法
    protected $action;
    
    protected $rqParamInst;
    
    public function setRqParams(RqParamsInterface $rqParamInst){
        $this->rqParamInst = $rqParamInst;
        $this->default();
    }

    /**
     * 20250201：默认路由
     */
    public function default(){
        $requestUri = $this->rqParamInst->uri();
        // 去除查询字符串
        $pRaw       = strtok($requestUri, '?');
        // 去除开头和结尾的斜杠
        // 由SHELL改写为argv
        if(isset($_SERVER['argv'])){
            // 获取命令行的参数
            global $argv;
            $path       = $argv[1] ? : '/';
        } else {
            $path       = trim($pRaw, '/');
        }
        $pathParts  = explode('/', $path);
        // 如果长度超4，第一个当公司key;
        if(count($pathParts) >= 4){
            $this->comKey = array_shift($pathParts); 
            // 公司信息会话初始化
            SystemCompany::sessionInit($this->comKey);
        }
        // 根据分割结果更新模块、控制器和方法
        $this->module       = Arrays::value($pathParts, '0')?:'index';
        $this->controller   = ucfirst(Strings::camelize(Strings::uncamelize((Arrays::value($pathParts, '1')?:'index'))));
        $this->action       = Arrays::value($pathParts, '2')?:'index';
    }
    /**
     * 解析后的控制器
     * @return string
     */
    public function controller(){
        return $this->controller;
    }
    /**
     * 解析后的方法
     * @return string
     */
    public function action(){
        return $this->action;
    }
    /**
     * 解析后的模块
     * @return string
     */
    public function module(){
        return $this->module;
    }
    /**
     * 20250203：公司key
     * @return type
     */
    public function comKey(){
        return $this->comKey;
    }
}

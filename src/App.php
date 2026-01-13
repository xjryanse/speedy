<?php
namespace xjryanse\speedy;

use xjryanse\speedy\facade\Route;
use Exception;

class App {
    // 20250202:默认的空控制器
    protected $emptyController = 'Error';
    /**
     * 程序入口调用；跨域处理，会话初始化
     */
    public static function init(){

    }
    
    /**
     * 程序运行
     * @return type
     */
    public function run (){
        // 加载控制器类文件
        return $this->loadClass();
    }

    /**
     * 20250127：加载控制器类
     * @return type
     * @throws Exception
     */
    protected function loadClass(){
        // 从路由实例获取模块、控制器、方法
        $module     = Route::module();
        $controller = Route::controller();
        $action     = Route::action();

        $class = 'app\\' . $module . '\\controller\\' . $controller;
        if(!class_exists($class)){
            // 当类不存在，映射空控制器
            $classStrRaw = $class;
            $controller = $this->emptyController;
            $class = 'app\\' . $module . '\\controller\\' . $controller;
            if(!class_exists($class)){
                throw new Exception("类".$classStrRaw."不存在");
            }
        }
        // 实例化控制器类
        $controllerInst = new $class();
        // 检查方法是否存在
        if (!method_exists($controllerInst, $action)) {
            throw new Exception("方法" . $action . "不存在".$class);
        }
        
        // 返回映射
        return $controllerInst->$action();
    }
}

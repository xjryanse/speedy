<?php

namespace xjryanse\speedy\service;

use Workerman\Worker;
use xjryanse\speedy\logic\Arrays;
/**
 * 2026年1月14日
 * 微服务的workerman启动
 */
class WorkerService {
    protected static $tcp;

    public static function start($port, $ip='0.0.0.0'){
        $url = 'tcp://' . $ip . ':' . $port;
        static::$tcp = new Worker($url);
        static::initOnWorkerStart();
        static::initOnMessage();
        Worker::runAll();
    }

    protected static function initOnWorkerStart(){
        // 20230331:使用定时器主动推送消息
        self::$tcp->onWorkerStart = function($worker){

        };
    }
    
    protected static function initOnMessage(){
        // 收到其他服务的调用请求时，处理业务逻辑
        self::$tcp->onMessage = function ($conn, $data) {
            // 接收请求，转发处理
            return static::onMsgLogic($conn, $data);
        };
    }
    /**
     * 消息逻辑
     */
    public static function onMsgLogic($conn, $data){
        // 一个url路由，一个传递参数
        $reqArr     = json_decode(trim($data), true);            
        $url        = Arrays::value($reqArr, 'url');
        $param      = Arrays::value($reqArr, 'param');

        $uArr   = explode('/',$url);

        if(count($uArr) <> 3){
            $respJson = static::response(1, 'url路径异常'.count($uArr));
            $conn->send($respJson);
        }

        // 拆解模块；控制器；方法
        $uModule        = $uArr[0];
        $uController    = $uArr[1];
        $uAction        = $uArr[2];

        $logic = '\\app\\'.$uModule.'\\logic\\'. ucfirst($uController).'Logic';
        $resp = $logic::$uAction($param);

        $conn->send(json_encode($resp));
    }
    

    public static function response($code, $msg, $data = []){
        $res = [
            'code'  => $code,
            'msg'   => $msg,
            'data'  => $data
        ];
        return json_encode($res);
    }
    
}

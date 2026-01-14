<?php

namespace xjryanse\speedy\tcp;

/**
 * tcp同步请求动作
 */
class Sync {
    /**
     * TCP同步调用
     */
    public static function request($host, $port, $send_data, $timeout = 3) {
        // 1. 建立TCP短连接
        $socket = stream_socket_client("tcp://{$host}:{$port}", $errno, $errmsg, $timeout);
        if (!$socket) {
            return ['code' => 503, 'msg' => "连接服务失败:{$errmsg}", 'data' => []];
        }
        // 2. 发送JSON字符串
        fwrite($socket, json_encode($send_data));
        // 3. 设置超时+接收响应
        stream_set_timeout($socket, $timeout);
        $response = fread($socket, 1024 * 20);
        // 4. 关闭连接
        fclose($socket);
        // 5. 解析响应并返回
        $res = json_decode(trim($response), true);
        return $res ?: ['code' => 400, 'msg' => '数据格式错误', 'data' => []];
    }
    
    
}

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
        // $response = fread($socket, 1024 * 20);
        
        // ========== 修复点2+3：循环读取全部数据，彻底解决截断问题 【核心修复】 ==========
        $response = '';
        $buffer_size = 1024 * 32; // 每次读取32k，可根据业务调整大小，不影响结果
        // 循环读取：直到读取到文件末尾(EOF)，就读完了全部数据
        while (!feof($socket)) {
            $buffer = fread($socket, $buffer_size);
            $response .= $buffer;
            // 防止无限循环，读到空数据直接退出
            if (empty($buffer)) {
                break;
            }
        }
        
        // 4. 关闭连接
        fclose($socket);
        // 5. 解析响应并返回
        $res = json_decode(trim($response), true);
        return $res;
    }
    
    
}

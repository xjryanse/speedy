<?php

namespace xjryanse\speedy\curl;

/**
 * 异步curl请求操作
 */
class Async {
    
    
    
    public static function get($url){
        return static::asyncCurlRequest($url);
    }
    
    public static function post($url,$param){
        $res = static::asyncCurlRequest($url,$param,'POST');
        return $res;
    }

    /**
     * 原生 CURL 实现异步请求（不等待接口返回）
     * @param string $url 接口地址
     * @param array $params 请求参数（GET 拼接在 URL，POST 放在请求体）
     * @param string $method 请求方法（GET/POST，默认 GET）
     * @param array $headers 请求头（默认空）
     * @return bool true=请求发送成功，false=发送失败（仅判断是否发出，不判断接口响应）
     */
    protected static function asyncCurlRequest(string $url, array $params = [], string $method = 'GET', array $headers = []) {
        // 1. 处理请求参数
        if (strtoupper($method) === 'GET' && !empty($params)) {
            // GET 方法：参数拼接在 URL 后
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }

        // 2. 初始化 CURL
        $ch = curl_init();
        if (!$ch) {
            return false;
        }

        // 3. 核心 CURL 配置（关键：非阻塞 + 短超时）
        $options = [
            // 目标 URL
            CURLOPT_URL => $url,
            // 不返回响应内容（无需等待，直接丢弃）
            CURLOPT_RETURNTRANSFER => true,
            // 禁用信号量（避免超时阻塞）
            CURLOPT_NOSIGNAL => true,
            // 超时时间：10秒（发送请求后立即断开，不等待响应）
            CURLOPT_TIMEOUT_MS => 10000,
            // 连接超时：500 毫秒（仅限制建立连接的时间，避免连接失败阻塞）
            CURLOPT_CONNECTTIMEOUT_MS => 500,
            // 忽略 SSL 证书错误（HTTPS 接口可选，生产环境建议开启证书验证）
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            // 关闭连接复用（避免后续请求受影响）
            CURLOPT_FORBID_REUSE => true,
            // 允许重定向（可选，根据接口需求调整）
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ];

        // 4. POST 方法配置（请求体 + Content-Type）
        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            // 处理参数：表单提交（application/x-www-form-urlencoded）
            // $postData = http_build_query($params);
            $postData = json_encode($params);
            // $postData = $params;
            // 若需 JSON 提交，替换为：$postData = json_encode($params);
            $options[CURLOPT_POSTFIELDS] = $postData;
            // 添加 Content-Type 头（表单提交或 JSON 提交二选一）
            $headers[] = 'Content-Type: application/json; charset=UTF-8';
            // JSON 提交需改为：$headers[] = 'Content-Type: application/json; charset=UTF-8';
        }

        // 5. 设置请求头（若有）
        if (!empty($headers)) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        // 6. 应用配置并执行请求
        curl_setopt_array($ch, $options);
        $resp = curl_exec($ch);

        // 7. 错误判断（仅判断请求是否成功发送，不关心接口响应）
        $errorNo = curl_errno($ch);
        curl_close($ch); // 立即关闭连接，释放资源

        return json_decode($resp,JSON_UNESCAPED_UNICODE);
//        
//        // 允许的错误码：超时（28）、连接超时（7）（因设置了 1ms 超时，正常发送后会触发超时）
//        $allowedErrors = [28, 7];
//        if ($errorNo === 0 || in_array($errorNo, $allowedErrors)) {
//            return true; // 请求已成功发送（即使触发 1ms 超时，也说明请求已发出）
//        }
//
//        // 其他错误（如 URL 无效、无法解析域名等）
//        // error_log("异步请求失败：URL={$url}，错误码={$errorNo}，错误信息=" . curl_error($ch));
//        return false;
    }
    
    
}

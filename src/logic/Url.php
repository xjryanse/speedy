<?php

namespace xjryanse\speedy\logic;

/**
 * url处理逻辑
 */
class Url {

    /**
     * 一段url取后缀名
     * @param type $url
     * @return type
     */
    public static function getExt($url) {
        $data = explode('?', $url);
        $basename = basename($data[0]);
        $basenames = explode('.', $basename);
        return isset($basenames[1]) ? $basenames[1] : '';
    }

    /**
     * 往url中添加参数
     * @param type $url     url
     * @param type $param   参数数组
     * @return string
     */
    public static function addParam($url, $param, $exceptParam = []) {
        //拆解参数
        $parseUrl = explode('?', $url);
        if (isset($parseUrl[1])) {
            //合并参数
            $param = array_merge(equalsToKeyValue($parseUrl[1]), $param);
        }
        //剔除参数
        if ($exceptParam) {
            foreach ($exceptParam as $key) {
                if (isset($param[$key])) {
                    unset($param[$key]);
                }
            }
        }
        //拼接参数
        $urlRes = $parseUrl[0];
        foreach ($param as $k => $v) {
            if (strstr($urlRes, '?')) {
                $urlRes .= '&' . $k . '=' . $v;
            } else {
                $urlRes .= '?' . $k . '=' . $v;
            }
        }
        return $urlRes;
    }

    /**
     * 空格转%20；中文转%
     * @param type $url
     * @return type
     */
    public static function encodeRaw($url) {
        $uri = '';
        $cs = unpack('C*', $url);
        $len = count($cs);
        for ($i = 1; $i <= $len; $i++) {
            $uri .= $cs[$i] > 127 ? '%' . strtoupper(dechex($cs[$i])) : substr($url, $i - 1, 1);
        }
        return $uri;
    }
    /**
     * 20250607:站点
     * @param type $url
     */
    public static function baseHost($url){
        $arr = parse_url($url);
        $base = $arr['scheme'].'://'.$arr['host'];
        if(isset($arr['port'])){
            $base .= ':'.$arr['port'];
        }
        $base .= '/';
        return $base;
    }
    
    /**
     * php 判断是否有效域名
     * @param type $domain
     */
    public static function isValidDomain($domain){
        // 域名正则表达式
        // 支持：
        // - 字母、数字、连字符(-)
        // - 各级域名用点(.)分隔
        // - 每个级域名长度1-63字符
        // - 顶级域名长度2-6字符
        // - 总长度不超过253字符
        $pattern = '/^(?=.{1,253}$)[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*\.[a-zA-Z]{2,6}$/';
        return preg_match($pattern, $domain) === 1;
    }
    /**
     * 从url中提取参数
     */
    public static function getParams($url){
        $query = parse_url($url, PHP_URL_QUERY);
        if(!$query){
            return [];
        }
        $params = [];
        parse_str($query, $params);
        return $params;
    }
}

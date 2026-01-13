<?php
namespace xjryanse\speedy\logic;

/*
 * 20251007:网络逻辑
 */
class Network {
    /**
     * 
     * @param type $ip
     * @return bool
     */
    public static function isLocalhostIp($ip) {
        // 特殊情况处理：localhost和127.0.0.1系列
        if ($ip === 'localhost' || strpos($ip, '127.') === 0) {
            return true;
        }
        // 获取服务器所有网络接口的IP地址
        $serverIps  = static::serverIps();
        // 检查目标IP是否在服务器IP列表中
        return in_array($ip, $serverIps);
    }
    
    /**
     * 获取服务器所有IP地址
     * @return array
     */
    public static function serverIps() {
        $ips = [];

        // 根据操作系统执行不同命令
        if (stristr(PHP_OS, 'WIN')) {
            // Windows系统
            exec('ipconfig | findstr /i "IPv4"', $output);
            foreach ($output as $line) {
                preg_match('/\d+\.\d+\.\d+\.\d+/', $line, $matches);
                if (!empty($matches[0])) {
                    $ips[] = $matches[0];
                }
            }
        } else {
            // Linux/Unix/Mac系统
            exec('hostname -I', $output);
            if (!empty($output[0])) {
                $ips = explode(' ', $output[0]);
            }

            // 备选方案（如果hostname命令不可用）
            if (empty($ips)) {
                exec('ifconfig | grep "inet " | grep -v 127.0.0.1', $output);
                foreach ($output as $line) {
                    preg_match('/inet (\d+\.\d+\.\d+\.\d+)/', $line, $matches);
                    if (!empty($matches[1])) {
                        $ips[] = $matches[1];
                    }
                }
            }
        }

        // 添加服务器环境变量中的IP
        if (!empty($_SERVER['SERVER_ADDR'])) {
            $ips[] = $_SERVER['SERVER_ADDR'];
        }

        // 去重并返回
        return array_unique($ips);
    }


}

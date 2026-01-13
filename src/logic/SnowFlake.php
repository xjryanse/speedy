<?php

namespace xjryanse\speedy\logic;

/**
 * 雪花算法
 */
class SnowFlake {

    // 1479533469598
    const EPOCH = 2599788969598;
    const max12bit = 4095;
    const max41bit = 1099511627775;

    static $machineId = 1;
    static $sequenceId = 0; //顺序id，取代随机
    static $lastTimeStamp = 0; //上次生成时间戳

    public static function machineId($mId) {
        static::$machineId = $mId;
    }
    /**
     * 20250720
     * @return int
     */
    public static function epoch(){
        return static::EPOCH;
    }

    /**
     * 
     * @param type $timestamp    时间戳 20241206：部分特殊指定，如分表
     * @return type
     */
    public static function generateParticle($timestamp = 0) {
        if ($timestamp) {
            $time = floor($timestamp * 1000);
        } else {
            $time = floor(microtime(true) * 1000);
        }
        $time       -= static::epoch();
        $base       = decbin(static::max41bit + $time);
        $machineid  = str_pad(decbin(static::getMachineId()), 10, "0", STR_PAD_LEFT);
        $sequence   = str_pad(decbin(static::getSequenceId($time)), 12, "0", STR_PAD_LEFT);
        $base       = $base . $machineid . $sequence;
        $id         =  bindec($base);
        // 20250719:不足19位补足19位
        return mb_strlen($id) < 19 ? str_pad($id, 19, '0', STR_PAD_LEFT) : $id;
    }

    /**
     * 获取顺序码
     */
    private static function getSequenceId($time) {
        if ($time == static::$lastTimeStamp) {
            static::$sequenceId += 1;
        } else {
            static::$sequenceId = 0;
        }
        static::$lastTimeStamp = $time;
        return static::$sequenceId;
    }

    /*
     * 获取机器码
     * 并发场景当作多个机器处理
     */

    private static function getMachineId() {
        //未指定机器id，则随机指派
        if (!static::$machineId) {
            static::$machineId = mt_rand(0, 1023);
        }
        return static::$machineId;
    }

    public static function timeFromParticle($particle) {
        $binaryIdRaw    = decbin($particle);
        // 20250719:调整为去除后面22位（与生成对应）
        $timeBinary     = substr($binaryIdRaw, 0, strlen($binaryIdRaw) - 22);
        return bindec($timeBinary) - static::max41bit + static::epoch();
    }

    /*
     * 20221003:获取时间戳
     */
    public static function getTimestamp($particle) {
        $microTime = static::timeFromParticle($particle);
        return intval($microTime / 1000);
    }

    /**
     * 计算年份
     * @param type $id
     */
    public static function getYear($id) {
        // 不是有效年份，则计算时间戳，返回年
        $timestamp = static::getTimestamp($id);
        return date('Y', $timestamp);
    }
}

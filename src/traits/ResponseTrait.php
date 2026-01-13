<?php

namespace xjryanse\speedy\traits;

use service\ConfigService;
use xjryanse\speedy\facade\Session;
use xjryanse\speedy\facade\Request;
use xjryanse\speedy\facade\Route;
use orm\sql\Sql;
use xjryanse\speedy\orm\DbOperate;

/**
 * 返回码复用
 */
trait ResponseTrait {

    /**
     * 成功返回
     */
    protected static function succReturn($msg = '请求成功', $data = '', $res = []) {
        // header('Content-Type: application/json; charset=utf-8');
        $res['code'] = 0;     //20191205 数据返回的基本结构   三个字段   code=0 ,message='提示', data=>{}
        $res['message'] = $msg;
        $res['data'] = $data;
        // 拼接开发模式参数
        $json = json(array_merge($res, static::devModeRes()));
        return $json;
//        // 20250824:优化压缩
//        header('Content-Encoding: gzip');
//        return gzencode($json);
    }

    /**
     * 失败返回
     */
    protected static function errReturn($msg = '请求失败', $data = '') {
        // header('Content-Type: application/json; charset=utf-8');

        $res['code'] = 1;
        $res['message'] = $msg;
        $res['data'] = $data;
        return json(array_merge($res, static::devModeRes()));
    }

    /**
     * 指定code返回
     */
    protected static function codeReturn($code = 999, $msg = '', $data = [], $trace = []) {
        // header('Content-Type: application/json; charset=utf-8');

        $res['code'] = $code;
        $res['message'] = $msg;
        $res['data'] = $data;
        // 20230727;输出错误信息
        if ($trace) {
            $res['trace'] = $trace;
        }

        return json(array_merge($res, static::devModeRes()));
    }

    /**
     * 失败返回
     */
    protected static function dataReturn($msg = '请求', $data = '') {
        if ($data) {
            return static::succReturn($msg . '成功', $data);
        } else {
            return static::errReturn($msg . '失败', $data);
        }
    }

    /**
     * 异常信息返回
     */
    protected function throwMsg(\Throwable $e) {
        /*
          $debug = Request::param('debug');
          if($debug == 'xjryanse'){
          $res['msg']     = $e->getMessage();
          $res['file']    = $e->getFile();
          $res['line']    = $e->getLine();
          $res['trace']   = $e->getTrace();

          return json($res);
          }
          return $this->errReturn( $e->getMessage() );
         * 
         */
    }

    /**
     * 分页兼容
     * @param type $res
     * @param type $msg
     * @return type
     */
    protected function paginateReturn($res) {
        if ($res) {
            $res = $res->toArray();
            return [
                'total_result' => $res['total'],
                'page_size' => $res['per_page'],
                'page_no' => $res['current_page'],
                'last_page' => $res['last_page'],
                'data' => $res['data'],
            ];
        }
    }

    /**
     * 20240419:开发模式参数
     * @return string
     */
    private static function devModeRes() {
        global $stime,$ctime;
        
        if (!ConfigService::config('isDevMode')) {
            // 20250206:调试方便注释
            // return [];
        }
        $res = [];
        $res['session_id']  = Session::getSessionid();
        $res['user_id']     = session(SESSION_USER_ID);
        $res['requestIp']   = Request::ip();
        $res['stime']       = $stime;
        $res['ctime']       = $ctime;
        $res['etime']       = time();
        $res['tDiff']       = time() - $stime;
        
        if (session('recUserId')) {
            $res['recUserInfo'] = UserService::mainModel()->where('id', session('recUserId'))->field('id,nickname')->cache(86400)->find();
        }
        
        $localPath = '';
        $codePath = 'http://localhost:8522/cmd.php?filePath=' . $localPath . '&startLine=1&host=' . Request::host();
        $res['NBCodePath'] = $codePath;
        
        // 20241117：方便调试
        $sqlKey = Request::param('sqlKey');
        if($sqlKey){
            $sqlId  = Sql::keyToId($sqlKey);
            $comKey = Route::comKey();
            $res['sqlEditPath']     = Request::domain().'/manage/#/'.$comKey.'/universal/pSqlDetail/'.$sqlId;
        }
        // 20250420
        $res['sqlArr']  = DbOperate::allSqlArr();
        $res['env']     = Request::env();

        
        return $res;
    }
}

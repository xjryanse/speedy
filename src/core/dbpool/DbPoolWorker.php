<?php

namespace xjryanse\speedy\core\dbpool;


/**
 * worker环境，数据库连接池
 * 全局单例，统一管理
 */
class DbPoolWorker {
    // 实例初始化
    use \xjryanse\speedy\core\dbpool\worker\InitTraits;
    // 数据库配置相关
    use \xjryanse\speedy\core\dbpool\worker\ConfTraits;
    // 连接池管理
    use \xjryanse\speedy\core\dbpool\worker\PoolTraits;
    // 连接池统计
    use \xjryanse\speedy\core\dbpool\worker\PoolStaticsTraits;
    // 连接池心跳管理
    use \xjryanse\speedy\core\dbpool\worker\PoolHeartBeatTraits;
    
}

<?php

namespace xjryanse\speedy\core\db;

/**
 * 数据库连接抽象类库
 */
abstract class Base {

    // 抽象方法：连接数据库
    abstract public function connect(array $config);

    // 抽象方法：执行查询
    abstract public function query(string $query, array $bind=[]);

    // 抽象方法：关闭连接
    abstract public function close();
    
}

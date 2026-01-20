<?php
namespace xjryanse\speedy\logic;

/**
 * Sql语句处理逻辑
 */
class Sql {

    /**
     * case when 语句创建
     * @param type $field   字段名
     * @param array $array  数组
     */
    public static function buildCaseWhen($field, array $array) {
        if ($array) {
            $str = "(CASE " . $field;
            foreach ($array as $key => $value) {
                $strVal = is_array($value) ? Arrays::value($value, 'label') : $value;
                $str .= " WHEN '" . $key . "' THEN '" . $strVal . "'";
            }
            $str .= " ELSE '' END)";
        } else {
            //解决$array 为空报错-20210115
            $str = $field . ' ';
        }

        return $str;
    }

    /**
     * groupConcat整理
     * @param type $tableName       表名
     * @param type $whereCondition  where条件
     * @param type $field           字段名
     * @param type $label           别名
     * @return string
     */
    public static function buildGroupConcat($tableName, $whereCondition, $field, $label) {
        $str = "( SELECT GROUP_CONCAT(" . $field . ")"
                . " as " . $label
                . " FROM " . $tableName
                . " WHERE " . $whereCondition . " )";
        return $str;
    }

    /**
     * 统计结果直接更新（使用内联）
     * @param type $mainTable       主表
     * @param type $mainField       主表字段
     * @param type $dtlTable        明细表
     * @param type $dtlStaticField  明细表统计字段
     * @param type $dtlUniField     明细表关联主表id的字段
     * @param type $dtlCon          明细表查询条件
     * @param type $staticCate      统计类型：sum;count
     * @return string
     */
    public static function staticUpdate($mainTable, $mainField, $dtlTable, $dtlStaticField, $dtlUniField, $dtlCon = [], $staticCate = 'sum') {
        // 明细表查询条件
        $whereCon = ModelQueryCon::conditionParse($dtlCon);
        $sql = "update " . $mainTable . " as staticMain ";
        $dtlSql = "select 
                    ifnull(sum( b.`" . $dtlStaticField . "` ),0) AS staticTotal,
                    main.id as " . $dtlUniField . " from " . $mainTable . " as main left join " . $dtlTable . " as b on main.id = b." . $dtlUniField;
        if ($whereCon) {
            $dtlSql .= " where " . $whereCon;
        }
        $dtlSql .= " group by  main.id";

        $sql .= " inner join (" . $dtlSql . ") as staticDtl set " . $mainField . " = staticDtl.staticTotal where staticMain.id = staticDtl." . $dtlUniField;
        return $sql;
    }

    /**
     * 只获取实体表，不获取视图
     */
    public static function getTable($database) {
        $sql = "SELECT
                    TABLE_NAME as `table`
                FROM
                    information_schema.`TABLES` 
                WHERE
                    table_schema = '" . $database . "'";
        return $sql;
    }

    public static function getColumn($table) {
        $sql = "DESCRIBE " . $table;
        return $sql;
    }
    
    /**
     * 2026年1月10日
     * 功能：从sqlwhere条件中，提取结构化的字段信息（一般用于下钻sql替换）
     array(3) {
        ["alias"] => string(3) "tuA"
        ["field"]  => string(9) "startTime"
        ["full"]  => string(13) "tuA.startTime"
     }
     */
    public static function parseSqlFieldStruct(string $sqlCondStr) {
        $sqlCondition = trim($sqlCondStr);
        $result = [
            'alias' => '', // 无表别名时，该值为空字符串
            'field' => '',
            'full' => ''
        ];
        if (empty($sqlCondition)) {
            return $result;
        }

        // 核心兼容正则【重点】：优先匹配「表别名.字段名」，匹配不到则匹配「纯字段名」
        // 正则优先级：带别名格式 > 无别名格式，杜绝误匹配
        $pattern = '/(?:(?P<table_alias>[a-zA-Z0-9_]+)\.)?(?P<field_name>[a-zA-Z0-9_]+)/';
        if (preg_match($pattern, $sqlCondition, $matches)) {
            $result['table_alias'] = $matches['table_alias'] ?? ''; // 无别名则为空
            $result['field_name'] = $matches['field_name'];
            $result['full_field'] = $matches[0]; // 带别名是tuA.startTime，无别名是startTime
        }

        return $result;
    }
    
    
    
}

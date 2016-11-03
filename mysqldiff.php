<?php

error_reporting(E_ERROR);

/**
 *
 */
class MysqlDiff
{

    private $_src_db;
    private $_dest_db;
    private $_src_scheme;
    private $_dest_scheme;
    private $_db;//当前db
    private $_src_config = [];
    private $_dest_config = [];

    function __construct($_src_config, $_dest_config)
    {
        $this->_src_config = $_src_config;
        $this->_dest_config = $_dest_config;
        $this->_src_db = $this->_connect($this->_src_config);
        $this->_dest_db = $this->_connect($this->_dest_config);
        $this->_db = $this->_src_db;
    }

    function __destruct()
    {
        $this->_close();
    }

    private function _connect($db)
    {
        //创建mysqli对象方式 1
        //屏蔽连接产生的错误
        $mysqli = new mysqli($db['host'], $db['user'], $db['pwd'], $db['db']);

        //只能用函数来判断是否连接成功
        if (mysqli_connect_errno()) {
            echo mysqli_connect_error();
        }

        //创建mysqli对象方式 2 可以设置一些参数
        $mysqli = mysqli_init();
        $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);//设置超时时间
        $mysqli->real_connect($db['host'], $db['user'], $db['pwd'], $db['db']);

        return $mysqli;
    }

    private function _close()
    {
        $this->_src_db->close();
        $this->_dest_db->close();
    }

    private function _setDbsrc()
    {
        $this->_db = $this->_src_db;
    }

    private function _setDbdest()
    {
        $this->_db = $this->_dest_db;
    }

    private function _fetch_all($rst)
    {
        for ($res = array(); $tmp = $rst->fetch_array(MYSQLI_ASSOC);)
            $res[] = $tmp;

        return $res;
    }

    private function _getDbScheme()
    {
        $table_fields = [];
        $sql = "SHOW TABLES";
        $rst = $this->_db->query($sql);
        $data = $this->_fetch_all($rst);
        $rst->free();

        foreach ($data as $key => $table_arr) {

            foreach ($table_arr as $key => $table) {
                $rst = $this->_db->query("SHOW FULL COLUMNS FROM $table");
                $fields = $this->_fetch_all($rst);
                $rst->free();
                $table_fields[] = [
                    'table'  => $table,
                    'fields' => $fields,
                ];

            }

        }

        return $table_fields;

    }

    private function _getTableArr($schemeArr)
    {
        $tableArr = [];
        foreach ($schemeArr as $value) {
            $tableArr[] = $value['table'];
        }

        return $tableArr;
    }

    private function _resetFieldsArr($fields)
    {
        $fieldsArr = [];
        foreach ($fields as $value) {
            $fieldsArr[$value['Field']] = $value;
        }

        return $fieldsArr;
    }

    private function _getFieldsArr($schemeArr)
    {
        $fieldsArr = [];
        foreach ($schemeArr as $value) {
            $fields = $this->_resetFieldsArr($value['fields']);
            $fieldsArr[$value['table']] = $fields;
        }

        return $fieldsArr;
    }

    private function _getChangedTable()
    {
        $fieldsArr = [];
        foreach ($schemeArr as $value) {
            $fieldsArr[] = [$value['table'] => $value['fields']];
        }

        return $fieldsArr;
    }

    private function _diffTable()
    {
        $srcTable = $this->_getTableArr($this->_src_scheme);
        $descTable = $this->_getTableArr($this->_dest_scheme);

        $removedTableArr = array_diff($srcTable, $descTable);

        $addTableArr = array_diff($descTable, $srcTable);

        $commonTableArr = array_intersect($descTable, $srcTable);

        $srcFieldsArr = $this->_getFieldsArr($this->_src_scheme);
        $descFieldsArr = $this->_getFieldsArr($this->_dest_scheme);

        $changedTable = [];
        foreach ($commonTableArr as $table) {
            $srcFieldsStr = serialize($srcFieldsArr[$table]);
            $descFieldsStr = serialize($descFieldsArr[$table]);
            if ($srcFieldsStr != $descFieldsStr) {
                $changedTable[] = $table;
            }

        }

        return [
            'added'   => $addTableArr,
            'changed' => $changedTable,
            'deleted' => $removedTableArr
        ];
    }

    //处理CreateTable SQL
    private function _getCreateTableSql($tableArr)
    {
        $this->_setDbdest();

        $create_table_sql = [];
        foreach ($tableArr as $table) {
            $sql = "SHOW CREATE TABLE $table ";
            $rst = $this->_db->query($sql);
            if ($rst) {
                $data = $rst->fetch_array(MYSQLI_ASSOC);
                $rst->free();
                $create_table_sql[] = $data['Create Table'];
            }
        }

        return implode("\r\n\r\n", $create_table_sql);
    }

    private function _diffField()
    {

        $removedTableArr = array_diff($srcTable, $descTable);

        $addTableArr = array_diff($descTable, $srcTable);

        $commonTableArr = array_intersect($descTable, $srcTable);

        $srcFieldsArr = $this->_getFieldsArr($this->_src_scheme);
        $descFieldsArr = $this->_getFieldsArr($this->_dest_scheme);

        $changedTable = [];
        foreach ($commonTableArr as $table) {
            $srcFieldsStr = serialize($srcFieldsArr[$table]);
            $descFieldsStr = serialize($descFieldsArr[$table]);
            if ($srcFieldsStr != $descFieldsStr) {
                $changedTable[] = $table;
            }

        }

        return [
            'added'   => $addTableArr,
            'changed' => $changedTable,
            'deleted' => $removedTableArr
        ];
    }

    //查找之前的字段
    private function _findPrevField($fields, $name)
    {
        $keys = array_keys($fields);

        $index = array_search($name, $keys);
        if ($index == 0) return null;

        return $keys[$index - 1];
    }

    private function _getField($field)
    {
        $fieldArr[] = $field['Type'];


        if ($field['Collation']) {
            $fieldArr[] = "COLLATE {$field['Collation']}";
        }

        if ($field['Null'] == 'NO') {
            $fieldArr[] = 'NOT NULL';
        }

        if ($field['Default']) {
            $fieldArr[] = "Default {$field['Default']}";
        }
        if ($field['Comment']) {
            $fieldArr[] = "COMMENT '{$field['Comment']}' ";
        }

        if ($field['Extra'] == 'auto_increment') {
            $fieldArr[] = " AUTO_INCREMENT ";
        }

        return implode(' ', $fieldArr);
    }

    private function _getAlterTableSql($tableArr)
    {
        $srcFieldsArr = $this->_getFieldsArr($this->_src_scheme);
        $destFieldsArr = $this->_getFieldsArr($this->_dest_scheme);

        //一个表一个表的处理
        $alter_table_sql = [];
        $addedSql = [];
        $deletSql = [];
        $changeSql = [];
        foreach ($tableArr as $table) {

            $srcFields = $srcFieldsArr[$table];
            $destFields = $destFieldsArr[$table];

            $addedDiff = array_diff_assoc($destFields, $srcFields);
            $deletedDiff = array_diff_assoc($srcFields, $destFields);

            $maybeChangeDiff = array_diff_assoc($destFields, $addedDiff);


            foreach ($addedDiff as $field => $value) {

                $afterField = $this->_findPrevField($destFields, $field);

                if ($afterField) $afterFieldStr = "AFTER `$afterField`";

                $fieldStr = $this->_getField($value);

                $indexStr = '';
                if ($value['Key'] == 'PRI') {
                    $indexStr = ",  ADD  PRIMARY KEY  (`{$value['Key']}`)";
                }
                // if($value['Key']=='INDEX'){
                // 	$indexStr= ",  ADD  INDEX   (`{$value['Key']}`)";
                // }
                // if($value['Key']=='UNIQI'){
                // 	$indexStr= ",  ADD  UNIQI   (`{$value['Key']}`)";
                // }

                $addedSql[] = "ALTER TABLE `$table` ADD COLUMN $field $fieldStr $afterFieldStr $indexStr ;";

            }

            foreach ($deletedDiff as $field => $value) {
                $deletSql[] = "ALTER TABLE `$table` DROP COLUMN $field ;";
            }


            foreach ($maybeChangeDiff as $field => $value) {
                $afterFieldStr = '';
                $diffFields = array_diff_assoc($destFields[$field], $srcFields[$field]);

                if (!empty($diffFields)) {

                    if ($srcFields[$field]['Key'] == 'PRI' && $destFields[$field] != 'PRI') {
                        $changeSql[] = "ALTER TABLE `$table` DROP PRIMARY KEY;";
                    }


                    if ($destFields[$field]['Key'] == 'PRI' && $srcFields[$field] != 'PRI') {
                        $changeSql[] = "ALTER TABLE `$table` ADD PRIMARY KEY (`$field`);";
                    }

                    $afterField = $this->_findPrevField($destFields, $field);

                    if ($afterField) {
                        $afterFieldStr = "AFTER `$afterField`";
                    }

                    $fieldStr = $this->_getField($value);

                    $changeSql[] = "ALTER TABLE `$table` CHANGE  `$field`  `$field` $fieldStr $afterFieldStr;";

                }
            }

        }

        $str = implode("\r\n", $addedSql) . "\r\n" . implode("\r\n", $deletSql) . "\r\n" . implode("\r\n", $changeSql);

        return $str;
    }

    private function _getDeletedTable($tableArr)
    {
        $this->_setDbdest();

        $deleted_table_sql = [];
        foreach ($tableArr as $table) {
            $sql = "DROP TABLE `$table`;";
            $deleted_table_sql[] = $sql;
        }

        return implode("\r\n\r\n", $deleted_table_sql);

    }

    public function diffDb()
    {
        $this->_setDbsrc();
        $this->_src_scheme = $this->_getDbScheme();
        $this->_setDbdest();
        $this->_dest_scheme = $this->_getDbScheme();

        $tableArr = $this->_diffTable();

        # Added
        echo "\r\n\r\n-- dest Tables Added \r\n";
        echo $this->_getCreateTableSql($tableArr['added']);


        # Changed
        echo "\r\n\r\n-- dest Tables changed \r\n";
        echo $this->_getAlterTableSql($tableArr['changed']);

        # deleted
        echo "\r\n\r\n-- dest Tables deleted \r\n";
        echo $this->_getDeletedTable($tableArr['deleted']);


    }
}

$src_config = ['host' => '127.0.0.1', 'user' => 'root', 'pwd' => '123456', 'db' => 'test'];//旧版数据库
$dest_config = ['host' => '127.0.0.1', 'user' => 'root', 'pwd' => '123456', 'db' => 'test_dest'];//修改过得数据库

$mysqldiff = new MysqlDiff($src_config, $dest_config);

$mysqldiff->diffDb();

//print_r($argv);





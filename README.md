# mysqldiff
比较旧版数据库和新版数据库字段更新，生成上线sql语句（不包括索引更新）

##example:

###配置数据库参数
在 mysqldiff.php里面配置好要比较的数据库，这里不用命令行的参数，是因为参数太多太长，不好复用，不如写在代码内。

    $src_config = ['host' => '127.0.0.1', 'user' => 'root', 'pwd' => '123456', 'db' => 'test'];//旧版数据库

    $dest_config = ['host' => '127.0.0.1', 'user' => 'root', 'pwd' => '123456', 'db' => 'test_dest'];//修改过得数据库

###在命令行执行 

`php mysqldiff.php`

结果如下：
      
        -- dest Tables Added 
        CREATE TABLE `add_table` (
          `id` int(11) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8

        -- dest Tables changed 
        ALTER TABLE `Student` ADD COLUMN age2 int(11) NOT NULL AFTER `ke`  ;
        ALTER TABLE `user` ADD COLUMN name2 varchar(255) COLLATE utf8_general_ci AFTER `id`  ;
        ALTER TABLE `user` ADD COLUMN testf int(11) NOT NULL Default 1 AFTER `name2`  ;
        ALTER TABLE `Student` DROP COLUMN age ;
        ALTER TABLE `user` DROP COLUMN name ;
        ALTER TABLE `Student` DROP PRIMARY KEY;
        ALTER TABLE `Student` CHANGE  `id`  `id` int(11) NOT NULL ;
        ALTER TABLE `Student` CHANGE  `name`  `name` varchar(30) COLLATE utf8_general_ci NOT NULL AFTER `age2`;
        ALTER TABLE `Student` ADD PRIMARY KEY (`height`);
        ALTER TABLE `Student` CHANGE  `height`  `height` int(11) NOT NULL  AUTO_INCREMENT  AFTER `name`;

        -- dest Tables deleted 
        DROP TABLE `BookRecord`;


注意:自己手动对比下数据，错了我不负责啊，😁

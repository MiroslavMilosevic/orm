<?php
require_once('ORM.php');

define('DB_CONFIG',  [
   'DB_HOST'=> 'localhost',
   'DB_USER'=> 'root',
   'DB_PASSWORD'=> '',
   'DB_NAME'=> 'current_project',
   'TABLE_NAME'=> 'users',
]);


$orm = new ORM(DB_CONFIG);


$raw_sql_group_by = 
$orm->fields('first_name', 'COUNT(*) as num_of_rows')
->c('country', '=', 'RS')->or()->c('country', '=', 'MS')->or()->c('country', '=', 'JA')->or()->c('country', '=', 'OK')->groupBy('first_name')
->having()->c('first_name', '<>', 'BBznVaTe')->and()->obr()->c('first_name', '<>', '3Fz1BkLSSq3Hi1')->cbr()
->orderBy('num_of_rows', 'DESC')
->select()->toRawSql();

echo '<pre>';
print_r($raw_sql_group_by);
echo '</pre>';

// $raw_sql = $orm->fields('email','first_name', 'email_verified_at')
// ->c('email', '=' ,'jovan@javas.com')->or()->obr()->c('email', 'LIKE', '%@%')->and()->c('email', '<>', 'miroslav.milosevic999@gmail.com')->cbr()
// ->orderBy('email_verified_at', 'ASC')
// ->select()->toRawSql();

// echo '<pre>';
// print_r($raw_sql);
// echo '</pre>';

$result_array = $orm->exectue();


echo '<pre>';
print_r($result_array);
echo '</pre>';



echo '<pre>';
print_r($orm->getConnErrors());
echo '</pre>';
?>
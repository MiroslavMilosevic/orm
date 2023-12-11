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




// echo '<pre>';
// print_r($orm->getTableStructure());
// echo '</pre>';


// $orm->fields('pid', 'eid')
// ->c('time_stamp', '>=' ,'2023-01-01')
// ->and()
//    ->obr()
//       ->c('time_stamp', '<' ,'2022-01-01')->or()->obr()->c('aff_id', '=' , '55151')->and()->c('player_id', '<>', '51234')->cbr()
//    ->cbr()->select();

echo '<pre>';
print_r($orm->fields('email','first_name', 'email_verified_at')
->c('email', '=' ,'jovan@javas.com')->or()
->obr()->c('email', 'LIKE', '%@%')->and()->c('email', '<>', 'miroslav.milosevic999@gmail.com')->cbr()
->orderBy('email_verified_at', 'ASC')
->select()->toRawSql());
echo '</pre>';
$player = $orm->exectue();


echo '<pre>';
print_r($player);
echo '</pre>';



echo '<pre>';
print_r($orm->getConnErrors());
echo '</pre>';
?>
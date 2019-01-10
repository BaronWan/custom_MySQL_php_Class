<?php
require_once "custom_MySQL_php_Class/database.class.php";

$db = new Database('myDBName');
$db->_action('select');

$db->table('tblName1')->fields(array('su.id','su.sessionName','u.nickname as userNickName','u.phone','u.email'))->_as('su')->_memory(True);
$db->join('tblName2')->_as('ss')->on(array('su.id'=>'ss.id'))->_memory(True);
$db->join('tblName3')->_as('u')->on(array('su.uid'=>'u.uid'))->order('su.id')->_memory(True);
try {
	$res = $db->execute(True);
}catch (Exception $e){
	echo $e->getMessage() ."\n";
}

var_dump($res);
?>

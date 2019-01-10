<?php
require_once "custom_MySQL_php_Class/database.class.php";

$db = new Database('smallchat_db');
$db->_action('select');

// $db->table('im_user')->fields(array('uid','nickname','password','phone','email'))->order('uid')->desc()->limit(20)->_memory(True);

$db->table('im_session_user')->fields(array('su.sessionid','ss.name as sessionName','u.nickname as userNickName','u.phone','u.email'))->_as('su')->_memory(True);
$db->join('im_session')->_as('ss')->on(array('su.sessionid'=>'ss.id'))->_memory(True);
$db->join('im_user')->_as('u')->on(array('su.uid'=>'u.uid'))->order('su.sessionid')->_memory(True);
try {
	$res = $db->execute(True);
}catch (Exception $e){
	echo $e->getMessage() ."\n";
}

var_dump($res);
?>

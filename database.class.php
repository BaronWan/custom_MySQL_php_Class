<?php
/**
 * Author: Wan, Pei-Zhi <boytools@outlook.com>
 * Updated of 10 Jan, 2019
 * 設計一新的資料庫存取架構
 */
define ('ROOT_PATH', __DIR__ );
class Database
{
	private $NT = null;
	private $NDT = null;
	
	private $action = '';
	private $_sqlStr = null;
	private $_sqLs = [];
	
	private $flags = array(
		'db' => False,
		'table' => False,
		'fields' => False,
		'where' => False,
		'set' => False,
		'join' => False,
		'as' => False,
		'on' => False,
		'values' => False,
		'group' => False,
		'order' => False,
		'limit' => False,
		'asc' => False,
		'desc' => False,
		);
	private $patten = "";
	private $Requires = array();
	private $Patten_module = array(
		'db' => '{db}',
		'table' => '{table}',
		'select' => 'SELECT',
		'update' => 'UPDATE',
		'insert' => 'INSERT INTO',
		'delete' => 'DELETE FROM',
		'fields' => '{fields} FROM',
		'where' => 'WHERE {where}',
		'join' => 'JOIN {join}',
		'on' => 'ON {on}',
		'as' => 'AS {as}',
		'set' => 'SET {set}',
		'values' => 'VALUES {values}',
		'group' => 'GROUP {group}',
		'order' => 'ORDER {order}',
		'limit' => 'LIMIT {limit}',
		'desc' => 'DESC',
		'asc' => 'ASC',
		);
	private $connection = null;
	private $SECRET = '0a635be498ef18514aada7f84b';
	
	/*
	 *'DB_TYPE'               =>  'mysql',     // 数据库类型
	* 'DB_HOST'               =>  '127.0.0.1', // 服务器地址
	* 'DB_NAME'               =>  'database',          // 数据库名
	* 'DB_USER'               =>  'username',      // 用户名
	* 'DB_PWD'                =>  'password',          // 密码
	* 'DB_PORT'               =>  '3306',        // 端口
	* 'DB_PREFIX'             =>  'prefix_',    // 数据库表前缀
	*/
	public function __construct($dbname) {
		$this->NT = intval(time());
		$this->NDT = date('d/m/Y H:i:s', $this->NT);
		
		$config = include ROOT_PATH .'/Conf/config.php';
		$config['DB_NAME'] =  empty($dbname) ? 'my_database' : $dbname;
		
		$db_patten = "{DB_TYPE}:host={DB_HOST};dbname={DB_NAME};unix_socket={DB_SOCK};charset=utf8";
		$connInfo = self::_myformat($db_patten, $config);
		try {
			$this->connection = new PDO($connInfo, $config['DB_USER'], $config['DB_PWD']);
			$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			// var_dump($this->connection);

		} catch (PDOException $e) {
			$this->connection = null;
			error_log(sprintf("%s; _DB_CONNECTION; [FAILURE]; %s;\n", $this->NDT, $e->getMessage()),0);
			return array('code'=>False, 'msg'=>'連線失敗 #99'); // MySQL 資料庫連線失敗
		}
	}

	/**
	 * 字串格式轉換
	 * Designer: Wan, Pei-Zhi
	 * @param string $patten; ex: "{a} to abc"
	 * @param array $data; ex: array('a'=>'hello')
	 * example result: "hello to abc"
	 */
	function _myformat($patten, $data) {
		if (preg_match_all("/\{\w+\}/", $patten, $matches)){
			if (count($matches[0]) > 0) {
				foreach ($matches[0] as $f) {
					$f = preg_replace("/\{(.*)\}/","\\1",$f);
					if (array_key_exists($f, $data)) {
							$patten = preg_replace("/\{".$f."\}/", $data[$f], $patten);
					} /*else { return array('code'=>False, 'msg'=>'格式轉換失敗'); }*/
				} // 轉換完成 -> 輸出
				return $patten;
			} else { // 找不到可轉換資訊
				return null;
			}
		} else { // 分析格式失敗
			return null;
		}
	} 

	/**
	 * 多層次字典型陣列轉換為 SQL 字串
	 * Designer: Wan, Pei-Zhi
	 * @param string $patten
	 * @param array $data
	 * Example:
	 *   [sample of update] $update_patten = "UPDATE FROM {table} SET {set} WHERE {where};";
			 $data = array (
					'table' => 'my_table',
					'set' => array ('a_key'=>'a_value', 'b_key'=>'b_value'),
					'where' => array (
							'OR' => array ('c_key'=>'c_value', 'd_key'=>'d_value'),
							'AND' => array ('e_key'=>'e_value', 'f_key'=>'f_value')
					)
			);
			// SELECT {fields} FROM {table1} INNER JOIN {table2} ON {on} ORDER {order} DESC;
			$Requires = array(
				'table1' => 'ofUser',
				'table2' => 'ofPresence',
				'fields' => array('{table1}.username as uid',
								  '{table1}.creationDate as ctime',
								  '{table1}.modificationDate as mtime',
								  '{table2}.offlineDate as offtime',
								  'IF({table2}.username IS NULL, TRUE, FALSE) as state'),
				'on' => array('{table1}.username'=>'{table2}.username'),
				'order' => 'state'
			);
			[sample of insert] $insert_patten = "INSERT INTO {table} ({fields}) VALUES {values};";
	 */
	function _multiArray2sqLstr($patten, $data, $dbg=False) {
		foreach($data as $key => $value){
			$cf = False;
			switch(preg_replace("/[\d+]/",'',$key)) {
				case 'on':
					$$key = '';
					foreach ($value as $k1 => $v1) {
						$$key .= sprintf('%s = %s', $k1, $v1);
					}
					$cf = True;
					break;
				
				case 'as':
					$$key = $value;
					$cf = True;
					break;
				
				case 'set':
					$$key = '';
					foreach ($value as $k1 => $v1) {
					  $$key .= is_numeric($v1) ? sprintf("%s = %d",$k1,$v1) : sprintf("%s = '%s'",$k1,$v1);
					  $$key .= ',';
					}
					$$key = preg_replace("/\,$/","",$$key);
					$cf = True;
					break;
				
				case 'fields':
					 $$key = '';
					 foreach ($value as $v){
					   $$key .= $v .',';
					 }
					 $$key = preg_replace("/\,$/","",$$key);
					 // print "[fields] ". $$key ."\n";
					 $cf = True;
					 break;
				
				case 'where':
					$$key = '';
					foreach ($value as $k1 => $v1) {
						$$key .= '(';
						foreach ($v1 as $k2 => $v2) {
							if (is_array($v2)) {
								foreach ($v2 as $k3 => $v3) {
									$cc = explode(" ",$v3);
									if (count($cc) >= 2){
										$$key .= sprintf("%s %s %s", $k3, $v3, $k1);
									} elseif (count($cc) == 1){
										$$key .= sprintf('%s LIKE "%%%s%%" %s ',$k3 ,$v3, $k1);
									}
								}
							} else {
								$cc = explode(" ",$v2);
								if (count($cc) >= 2){
									$$key .= sprintf("%s %s %s", $k2, $v2, $k1);
								} elseif (count($cc) == 1){
									$$key .= sprintf('%s LIKE "%%%s%%" %s ',$k2,$v2,$k1);
								}
							}
						}
						$$key = preg_replace("/ ".$k1." $/", "", $$key) .") AND ";
					}
					$$key = preg_replace("/ AND $/","",$$key);
					$cf = True;
					break;
				
				case 'values':
					 $$key = '';
					 $$key .= sprintf("(%s) VALUES ", implode(",", array_keys($value)));
					 $vs = array_values($value);
					 $c = count($vs[0]);
					 for ($i=0; $i<$c; $i++) {
						$$key .= '(';
						foreach ($value as $k2 => $v2) {
							$$key .= is_numeric($v2[$i]) ? $v2[$i] : sprintf("'%s'", $v2[$i]);
							$$key .= ",";
						}
						$$key = preg_replace("/\,$/",'',$$key);
						$$key .= '),';
					 }
					 $$key = preg_replace("/\,$/",'',$$key);
					 $cf = True;
					 break;
				
				case 'join':
				  /*
					$$key = '(SELECT {fields} FROM {table} AS {as})';
					foreach ($value as $k1 => $v1){
						switch($k1){
							case 'fields':
								$$k1 = '';
								foreach ($v1 as $k2 => $v2){
									$$k1 .= $v2 .',';
								}
								$$k1 = preg_replace("/\,$/", "", $$k1);
								break;
							
							case 'table':
							case 'as':
								$$k1 = $v1;
								break;
						}
						$$key = preg_replace("/\{".$k1."\}/", $$k1, $$key);
					}
					*/
					$$key = $value;
					$cf = True;
					break;
			   
				case 'group':
				case 'order':
					  $$key = "BY ". $value;
					  $cf = True;
					  break;
					
				case 'limit':
					 $$key = $value;
					 $cf = True;
					 break;
					
				case 'asc':
					$$key = $value;
					$cf = True;
					break;
				
				case 'desc':
					$$key = $value;
					$cf = True;
					break;
			}
			if ($cf) 
				$patten = preg_replace("/\{".$key."\}/",$$key,$patten);
		}
		foreach($data as $k => $v) {
			if (!is_array($v)) {
				$patten = preg_replace("/\{".$k."\}/", $v, $patten);
			}
		}
		return $patten;
	}
	/* :PUT:
	 * @param string $name
	 */
	public function dbname($name){
		$this->Requires['db'] = $name;
		$this->flags['db'] = True;
		return $this;
	}
	/* :PUT:
	 * @param string $name
	 */
	public function table($name){
		$this->Requires['table'] = $name;
		$this->flags['table'] = True;
		return $this;
	}
	/* :PUT:
	 * @param array $data
	 * $data = array('f1','f2',f3',...);
	 */
	public function fields($data){
		$this->Requires['fields'] = $data;
		$this->flags['fields'] = True;
		return $this;
	}
	/* :PUT:
	 * @param array $where
	 * $where = array(
		'AND' => array(
			'k1' => 'v1',
			'k2 >' => 'v2'
		),
		'OR' => array(
			'k3' => 'v3'
		),
	 )
	 */
	public function where($where){
		$this->Requires['where'] = $where;
		$this->flags['where'] = True;
		return $this;
	}
	/* :PUT:
	 * @param string $field
	 */ 
	public function group($field){
		$this->Requires['group'] = $field;
		$this->flags['group'] = True;
		return $this;
	}
	/* :PUT:
	 * @param string $field
	 */
	public function order($field){
		$this->Requires['order'] = $field;
		$this->flags['order'] = True;
		return $this;
	}	
	/* :PUT:
	 * @param integar $num
	 */
	public function limit($num){
		$this->Requires['limit'] = $num;
		$this->flags['limit'] = True;
		return $this;
	}
	/* :PUT:
	 * @param array $data
	 * $data = array(
				'flelds' => array('f1','f2'),
				'table' => 'name',
				'as' => 'alias'
			 )
	 *	result_patten = '(SELECT {fields} FROM {table} AS {as})';
	 */
	public function join($data){
		$this->Requires['join'] = $data;
		$this->flags['join'] = True;
		return $this;
	}
	/* :PUT:
	 * @param string $name
	 */
	public function _as($name){
		$this->Requires['as'] = $name;
		$this->flags['as'] = True;
		return $this;
	}
	/* :PUT:
	 * @param array $data
	 */
	public function on($data){
		$this->Requires['on'] = $data;
		$this->flags['on'] = True;
		return $this;
	}
	/* :PUT:
	 * @param array $data
	 */
	public function set($data){
		$this->Requires['set'] = $data;
		$this->flags['set'] = True;
		return $this;
	}
	/* :PUT:
	 * @param array $data
	 * $data = array(
		'f1' => array('f1v1','f1v2','f1v3'),
		'f2' => array('f2v1','f2v2','f2v3'),
	 )
	 */
	public function values($data){
		$this->Requires['values'] = $data;
		$this->flags['values'] = True;
		return $this;
	}
	public function asc(){
		$this->Requires['asc'] = 'ASC';
		$this->flags['asc'] = True;
		return $this;
	}
	public function desc(){
		$this->Requires['desc'] = 'DESC';
		$this->flags['desc'] = True;
		return $this;
	}
	/* :SET:
	 * @param string $name
	 */
	public function _action($name){
		$this->action = $name;
		return $this;
	}
	/* :GET:
	 * to record
	 */
	public function _memory($dbg=False){
		$listSort = array(
			'select' => array('fields','table','join','as','on','where','group','order','asc','desc','limit'),
			'update' => array('table','set','where'),
			'insert' => array('table','values'),
			'delete' => array('table','where'),
		);
		if (empty($this->_sqLs)) {			
			$this->Patten = $this->Patten_module[$this->action] .' ';
		}
		foreach ($listSort[$this->action] as $keyName) {
			($dbg) ? printf("Find: %s\n", $this->Patten_module[$keyName]) : NULL;
			if ( in_array($keyName ,array_keys($this->Requires)) ) {
				if ($keyName == 'join'){
					printf("\nGET JOIN INFO: %s\n\n", $this->Patten_module[$keyName]);
				}
				$this->Patten .= $this->Patten_module[$keyName] .' ';
			
			}
		}

		($dbg) ? printf("Patten Value: \" %s \"\n", $this->Patten) : NULL;	
		// ($dbg) ? var_dump($this->_multiArray2sqLstr($this->Patten, $this->Requires)) : NULL;
		
		array_push($this->_sqLs, $this->_multiArray2sqLstr($this->Patten, $this->Requires));
		$this->Patten = null;
		$this->Requires = array();
		// 恢復原值
		foreach($this->flags as $kk){
			$this->flags[$kk] = False;
		}	
		($dbg) ? var_dump($this->_sqLs) : NULL ;
		return $this;
	}
	/* :PUT:
	 * Specify a SQL string
	 */
	public function _specSqlStr($sqlstr, $dbg=False){
		($dbg) ? printf("GETTED SQL_STRING: %s\n", $sqlstr) : NULL;
		$this->_sqlStr = $sqlstr .";";
		return $this;
	}
	/* :EXEC:
	 * execution
	 */
	public function execute($dbg=False){
		$chkout = function($dbg=False) {
			switch ($this->action) {
				case 'select':
					try {
						$stmt = $this->connection->prepare($this->_sqlStr);
						$this->_sqlStr = null;
					} catch (PDOException $e) {
						echo $e->getMessage() ."\n";
						return False;
					}
					if($dbg){var_dump($stmt);}
					if ($stmt) {
						$stmt->execute();
						$res = $stmt->setfetchMode(PDO::FETCH_ASSOC);
						// var_dump($stmt->fetchAll());
						return $stmt->fetchAll();
					} else {
						echo self::get_error() ."\n";
						return False;
					}
					break;
	
				case 'delete':
				case 'update':
				case 'insert':
					try {
						$stmt = $this->connection->prepare($this->_sqlStr);
						$this->_sqlStr = null;
					} catch (PDOException $e) {
						return False;
					}
					if ($stmt) {
						if ($stmt->execute()) {
							return True;
						} else {
							echo self::get_error() ."\n";
							return False;
						}
					} else {
						echo self::get_error() ."\n";
						return False;
					}
					break;
			}
		};
		$this->_specSqlStr( (!empty($this->_sqLs) ? implode(' ', $this->_sqLs) : $this->_sqlStr) ,$dbg);
		$this->_sqLs = [];
		
		if (!empty($this->_sqlStr)) {
			return $chkout();
		
		} elseif (empty($this->Requires)) {
			if (empty($this->_sqlStr)) {
				return False;
			}
			return $chkout();
			
		} elseif (!empty($this->Patten)){
			if (empty($this->_sqlStr)) {
				return False;
			}
			return $chkout();
		
		} else {
			return False;
		}
	}
	/* :EXEC:
	 * select
	 */
	public function select($dbg=False) {
		$this->_action('select');
		$this->_memory($dbg);
		return $this->execute($dbg);
	}
	/* :EXEC:
	 * update
	 */
	public function update($dbg=False) {
		$this->_action('update');
		$this->_memory($dbg);
		return $this->execute($dbg);
	}
	/* :EXEC:
	 * insert
	 */
	public function insert($dbg=False) {
		$this->_action('insert');
		$this->_memory($dbg);
		return $this->execute($dbg);
	}
	/* :EXEC:
	 * delete
	 */
	public function delete($dbg=False) {
		$this->_action('delete');
		$this->_memory($dbg);
		return $this->execute($dbg);
	}
	
	///* :GET: SELECT
	// */
	//public function select($dbg=False){
	//	$patten = 'SELECT ';
	//	foreach (array('fields','table','join','as','on','where','group','order','asc','desc','limit') as $kl => $vl){
	//		if ($this->flags[$vl]){
	//			$patten .= $this->Patten_module[$vl] .' ';
	//		} // end of if
	//	} // end of foreach
	//	if($dbg){ var_dump($patten); var_dump($this->Requires); }
	//	
	//	$res = $this->exec('select', $patten, $this->Requires, $dbg);
	//	
	//	// 恢復原值
	//	foreach($this->flags as $kk){
	//		$this->flags[$kk] = False;
	//	}
	//	
	//	if($dbg){ var_dump($res); }
	//	
	//	if (!$res['code']){
	//		return null;
	//	} else {
	//		return $res['data'];
	//	}
	//}
	//
	///* :SET: UPDATE
	// */
	//public function update($dbg=False){
	//	$patten = '';
	//	foreach (array('table','set','where') as $kl => $vl){
	//		if ($this->flags[$vl]){
	//			if ($vl === 'table'){
	//				$patten .= sprintf('UPDATE {%s} ', $vl);
	//			} else {
	//				$patten .= $this->Patten[$vl] .' ';
	//			}
	//			
	//		}
	//	}
	//	if($dbg){ var_dump($patten); }
	//	$res = $this->exec('update', $patten, $this->Requires, $dbg);
	//	foreach($this->flags as $kk){
	//		$this->flags[$kk] = False;
	//	}
	//	if (!$res['code']){
	//		if ($dbg){ echo $res['msg'] ."\n"; }
	//		return False;
	//	} elseif ($res['code']) {
	//		return True;
	//	}
	//}
	//
	///* :SET: INSERT
	// */
	//public function insert($dbg=False){
	//	$patten = '';
	//	foreach (array('table','fields','values') as $kl => $vl){
	//		if ($this->flags[$vl]){
	//			if ($vl === 'table'){
	//				$patten .= sprintf('INSERT {%s} ', $vl);
	//			} elseif ($vl === 'fields') {
	//				$patten .= sprintf('(%s) ', $vl);
	//			} else {
	//				$patten .= $this->Patten[$vl] .' ';
	//			}
	//		}
	//	}
	//	if($dbg){ var_dump($patten); }
	//	$res = $this->exec('insert', $patten, $this->Requires, $dbg);
	//	foreach($this->flags as $kk){
	//		$this->flags[$kk] = False;
	//	}
	//	if (!$res['code']){
	//		if ($dbg){ echo $res['msg'] ."\n"; }
	//		return False;
	//	} elseif ($res['code']) {
	//		return True;
	//	}
	//}
	//
	///* :SET: DELETE
	// */
	//public function delete($dbg=False){
	//	$patten = '';
	//	foreach (array('table','where') as $kl => $vl){
	//		if ($this->flags[$vl]){
	//			if ($vl === 'table'){
	//				$patten .= sprintf('DELETE FROM {%s} ', $vl);
	//			} else {
	//				$patten .= $this->Patten[$vl] .' ';
	//			}
	//		}
	//	}
	//	if($dbg){ var_dump($patten); }
	//	$res = $this->exec('delete', $patten, $this->Requires, $dbg);
	//	foreach($this->flags as $kk){
	//		$this->flags[$kk] = False;
	//	}
	//	if (!$res['code']){
	//		if ($dbg){ echo $res['msg'] ."\n"; }
	//		return False;
	//	} elseif ($res['code']) {
	//		return True;
	//	}
	//}
	//
	///**
	// * 進行 SQLite3 資料庫存取主程式
	// * Designer: Wan, Pei-Zhi
	// * @param string $opt ;ex: (insert|insert|update|delete)
	// * @param string $patten
	// * @param array $data
	// */
	//public function exec ($opt, $patten, $data, $dbg=False) {
	//	$query = $this->_multiArray2sqLstr($patten, $data, $dbg);
	//	if($dbg){printf ("\n\n%s\n\n",$query);}
	//	switch ($opt) {
	//		case 'select':
	//				try {
	//						$stmt = $this->connection->prepare($query);
	//				} catch (PDOException $e) {
	//						return array('code'=>False, 'msg'=>$e->getMessage());
	//				}
	//				if($dbg){var_dump($stmt);}
	//				if ($stmt) {
	//						$stmt->execute();
	//						$res = $stmt->setfetchMode(PDO::FETCH_ASSOC);
	//						// var_dump($stmt->fetchAll());
	//						return array('code'=>True, 'data'=>$stmt->fetchAll() );
	//				} else {
	//						return array('code'=>False, 'msg'=> self::get_error() );
	//				}
	//				break;
	//
	//		case 'delete':
	//		case 'update':
	//		case 'insert':
	//				try {
	//						$stmt = $this->connection->prepare($query);
	//				} catch (PDOException $e) {
	//						return array('code'=>False, 'msg'=>$e->getMessage() );
	//				}
	//				if ($stmt) {
	//						if ($stmt->execute()) {
	//							return array('code'=>True);
	//						} else {
	//							return array('code'=>False, 'msg'=> self::get_error() );
	//						}
	//				} else {
	//						return array('code'=>False, 'msg'=> self::get_error() );
	//				}
	//				break;
	//	}
	//} // end function

	public function get_error() {
		$this->connection->errorInfo();
	}

	public function __destruct() {
		$this->connection = null;
	}
} // end class

?>

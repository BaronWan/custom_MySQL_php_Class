# CustomMySQLphpClass
設計一方便存取各種需求的 MySQL 樣板

## 設定檔  
- config.php  
程式中預設是在當前路徑的 Conf/  
```php  
52:		$config = include ROOT_PATH .'/Conf/config.php';  
```  

## 使用範例  
- 基本 select 用法  
```php  
$Patten = 'SELECT {fields} FROM {table} WHERE {where} LIMIT {limit};';
$Require = array(
  "table" => "my_project1",
  "fields" => array("name","password","email"),
  "where" => array(
    "AND" => array(
      "name" => "janness",
    ),
  ),
  "limit" => 1
);
```  
- 較複雜 select 用法  
```php  
$Patten = "SELECT {fields1} FROM {table1} AS {as1} JOIN (SELECT {fields2} FROM {table2}) AS {as2} ON {as1}.uid={as2}.uid WHERE {where} GROUP {group};";
$Require = array(
			'table1' => 'notice',
			'table2' => 'user',
			'as1' => 'n',
			'as2' => 's',
			'fields1' => array('{as1}.uid','{as2}.nickname AS name'),
			'fields2' => array('uid','nickname'),
			'where' => array('AND' => array(
          '{as1}.type' => 35,
          '{as1}.toUid' => $uid,
          '{as1}.status' => 1
        )
      ),
			'group' => '{as1}.uid'
);
```  


<?php 
class DB{

	/**
	 * Объект PDO.
	 */
	public static $dbh = null;
 
	/**
	 * Statement Handle.
	 */
	public static $sth = null;
 
	/**
	 * Выполняемый SQL запрос.
	 */
	public static $query = '';
 

 
	/**
	 * Добавление в таблицу, в случаи успеха вернет вставленный ID, иначе 0.
	 */
	public static function add($query, $param = array())
	{
		self::$sth = self::getDbh()->prepare($query);
		return (self::$sth->execute((array) $param)) ? self::getDbh()->lastInsertId() : 0;
	}
	
	/**
	 * Выполнение запроса.
	 */
	public static function set($query, $param = array())
	{
		//self::$sth = self::getDbh()->prepare($query);
		//return self::$sth->execute((array) $param);
		return false;
	}
	
	/**
	 * Получение строки из таблицы.
	 */
	public static function getRow($query, $param = array())
	{
		//self::$sth = self::getDbh()->prepare($query);
		//self::$sth->execute((array) $param);
		//return self::$sth->fetch(PDO::FETCH_ASSOC);		
		return false;
	}
	
	/**
	 * Получение всех строк из таблицы.
	 */
	public static function getAll($query, $param = array())
	{
		//self::$sth = self::getDbh()->prepare($query);
		//self::$sth->execute((array) $param);
		//return self::$sth->fetchAll(PDO::FETCH_ASSOC);	
		return false;
	}
	
	/**
	 * Получение значения.
	 */
	public static function getValue($query, $param = array(), $default = null)
	{
	//	$result = self::getRow($query, $param);
	//	if (!empty($result)) {
	//		$result = array_shift($result);
	//	}
 
	//	return (empty($result)) ? $default : $result;	
	return false;
	}
	
	/**
	 * Получение столбца таблицы.
	 */
	public static function getColumn($query, $param = array())
	{
		//self::$sth = self::getDbh()->prepare($query);
		//self::$sth->execute((array) $param);
		//return self::$sth->fetchAll(PDO::FETCH_COLUMN);	
		return false;
	}
}
<?php
namespace Core\Database;

class ClassNoDB
{
    /**
     * Объект PDO (заглушка).
     */
    public static $dbh = null;

    /**
     * Statement Handle (заглушка).
     */
    public static $sth = null;

    /**
     * Выполняемый SQL запрос (заглушка).
     */
    public static $query = '';

    /**
     * Возвращает true, чтобы имитировать успешное выполнение в случае добавления.
     */
    public static function add($query, $param = array())
    {
        self::$query = $query;
        // Вернуть фиктивный ID, если запрос выполнен (симуляция успешного выполнения)
        return 1; // Имитируем успешное добавление, возвращая ID = 1
    }

    /**
     * Имитирует выполнение запроса.
     */
    public static function set($query, $param = array())
    {
        self::$query = $query;
        // Имитация успешного выполнения запроса
        return true; // Успешно
    }

    /**
     * Имитирует получение одной строки из таблицы.
     */
    public static function getRow($query, $param = array())
    {
        self::$query = $query;
        // Возвращаем фиктивные данные
        return ['id' => 1, 'name' => 'Test']; // Пример возвращаемого результата
    }

    /**
     * Имитирует получение всех строк из таблицы.
     */
    public static function getAll($query, $param = array())
    {
        self::$query = $query;
        // Возвращаем фиктивные данные
        return [
            ['id' => 1, 'name' => 'Test 1'],
            ['id' => 2, 'name' => 'Test 2'],
        ]; // Пример возвращаемого результата
    }

    /**
     * Имитирует получение значения.
     */
    public static function getValue($query, $param = array(), $default = null)
    {
        self::$query = $query;
        // Возвращаем имитацию значения
        return 'Test Value'; // Пример возвращаемого результата
    }

    /**
     * Имитирует получение столбца таблицы.
     */
    public static function getColumn($query, $param = array())
    {
        self::$query = $query;
        // Возвращаем фиктивные данные
        return ['Value 1', 'Value 2']; // Пример возвращаемого результата
    }
}

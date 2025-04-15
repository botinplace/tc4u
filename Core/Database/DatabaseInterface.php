<?php

namespace Core\Database;

interface DatabaseInterface
{
	public static function add(string $sql, array $params = []);
	public static function set(string $sql, array $params = []);
	public static function getRow(string $sql, array $params = []);
	public static function getAll(string $sql, array $params = []);
	public static function getValue(string $sql, array $params = []);
	public static function getColumn(string $sql, array $params = []);
}

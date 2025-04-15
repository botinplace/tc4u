<?php

namespace Core\Database;

interface DatabaseInterface
{
	public function add(string $sql, array $params = []);
	public function set(string $sql, array $params = []);
	public function getRow(string $sql, array $params = []);
	public function getAll(string $sql, array $params = []);
	public function getValue(string $sql, array $params = []);
	public function getColumn(string $sql, array $params = []);
}

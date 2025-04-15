<?php
namespace Core;

use Core\DB\DatabaseFactory;

abstract class Model {
    protected $db;

    public function __construct($dbType) {
        $this->db = DatabaseFactory::create($dbType);
    }
}

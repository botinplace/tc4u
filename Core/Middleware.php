<?php

namespace Core;

use Core\Request;

abstract class Middleware{
    abstract public function handle(array $params, array $pagedata);
}

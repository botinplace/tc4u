<?php
namespace Core\Middleware;

interface MiddlewareInterface
{
    public function handle(array $params, array $pageData);
}

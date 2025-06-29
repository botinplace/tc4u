<?php
namespace Core;

class Route {    
    public $method;
    public $path;
    public $controller;
    public $needAuth;
    public $onlyforguest;
    public $middlewares;
    public $name;

    public function __construct( $method, $path, $controller, $needAuth = false,$onlyforguest = false,$name='',$middlewares=[]) {
       $this->method = strtoupper($method);
       $this->path = $this->normalizePath($path);
       $this->controller = $controller;
       $this->needAuth = $needAuth;
       $this->onlyforguest = $onlyforguest;
       $this->middlewares = $middlewares;
       $this->name = $name;
    }
	public static function __set_state(array $state) {
			$obj = new self( $state['method'], $state['path'], $state['controller'], $state['needAuth'], $state['onlyforguest'],$state['name'],$state['middlewares'] );			
			return $obj;
	}
	
    private function normalizePath(string $path): string {
        return '/' . trim(preg_replace('#[/]{2,}#', '/', $path), '/');
    }
}

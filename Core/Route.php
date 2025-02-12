<?php
namespace Core;

class Route {    
    public $method;
    public $path;
    public $controller;
    public $needAuth;
    public $middlewares;
    public $name;

    public function __construct( $method, $path, $controller, $needAuth = false,$name='') {
       $this->method = strtoupper($method);
       $this->path = $this->normalizePath($path);
       $this->controller = $controller;
       $this->needAuth = $needAuth;
       $this->middlewares = [];
       $this->name = $name;
    }
	public static function __set_state(array $state) {
			$obj = new self( $state['method'], $state['path'], $state['controller'], $state['needAuth'],$state['name'] );			
			return $obj;
	}
	
    private function normalizePath(string $path): string {
        return '/' . trim(preg_replace('#[/]{2,}#', '/', $path), '/');
    }
}

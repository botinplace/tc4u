<?php
namespace Core;

class Route {
    public $id;
    public $method;
    public $path;
    public $controller;
    public $template;
    public $contentFile;
    public $needAuth;
    public $middlewares;

    public function __construct($id, $method, $path, $controller, $template = null, $contentFile = null, $needAuth = false) {
       $this->id = $id;
       $this->method = strtoupper($method);
       $this->path = $this->normalizePath($path);
       $this->controller = $controller;
       $this->template = $template;
       $this->contentFile = $contentFile;
       $this->needAuth = $needAuth;
       $this->middlewares = [];
    }
	public static function __set_state(array $state) {
			$obj = new self( $state['id'], $state['method'], $state['path'], $state['controller'], $state['template'], $state['contentFile'], $state['needAuth'] );			
			return $obj;
	}
	
    private function normalizePath(string $path): string {
        return '/' . trim(preg_replace('#[/]{2,}#', '/', $path), '/');
    }
}

<?php
namespace Core;

class Container {
    private $bindings = [];
    private $instances = [];
    private $singletons = [];

    public function bind(string $abstract, $concrete = null): void {
        $this->bindings[$abstract] = $concrete ?? $abstract;
    }
    
    public function singleton(string $abstract, $concrete = null): void {
        $this->bindings[$abstract] = $concrete ?? $abstract;
        $this->singletons[$abstract] = true;
    }
    
    public function get(string $abstract) {
        return $this->make($abstract);
    }

    public function make(string $abstract, array $parameters = []) {
        // Проверка синглтонов
        if (isset($this->singletons[$abstract]) && isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Создание экземпляра
        $concrete = $this->bindings[$abstract] ?? $abstract;
        
        if (is_callable($concrete)) {
            $instance = $concrete($this, ...$parameters);
        } else {
            $reflector = new \ReflectionClass($concrete);
            $constructor = $reflector->getConstructor();
            
            $dependencies = [];
            if ($constructor) {
                foreach ($constructor->getParameters() as $param) {
                    $type = $param->getType();
                    $dependencies[] = $type && !$type->isBuiltin() 
                        ? $this->make($type->getName())
                        : ($parameters[$param->getName()] ?? null);
                }
            }
            
            $instance = $reflector->newInstanceArgs($dependencies);
        }

        // Сохранение синглтона
        if (isset($this->singletons[$abstract])) {
            $this->instances[$abstract] = $instance;
        }

        return $instance;
    }
}
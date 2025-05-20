<?php
// Core/Container.php
namespace Core;

class Container {
    private $bindings = [];
    private $instances = [];

    public function bind(string $abstract, $concrete = null): void {
        if ($concrete === null) {
            $concrete = $abstract;
        }
        $this->bindings[$abstract] = $concrete;
    }

    public function make(string $abstract, array $parameters = []) {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->bindings[$abstract] ?? $abstract;

        if (is_callable($concrete)) {
            return $concrete($this, ...$parameters);
        }

        $reflector = new \ReflectionClass($concrete);
        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return new $concrete();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->make($type->getName());
            } else {
                $dependencies[] = $parameters[$param->getName()] ?? null;
            }
        }

        $instance = $reflector->newInstanceArgs($dependencies);
        $this->instances[$abstract] = $instance;
        
        return $instance;
    }
}

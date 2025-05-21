<?php
// Core/Container.php
namespace Core;

class Container {
    private $bindings = [];
    private $instances = [];
    private $singletons = [];
    private $reflectionCache = [];
    private $resolving = [];

    public function bind(string $abstract, $concrete = null): void {
        $this->bindings[$abstract] = $concrete ?? $abstract;
    }

    public function singleton(string $abstract, $concrete = null): void {
        $this->bind($abstract, $concrete);
        $this->singletons[$abstract] = true;
    }

    public function get($id) {
        return $this->make($id);
    }
    
    public function has($id): bool {
        return isset($this->bindings[$id]) || class_exists($id);
    }

    public function make(string $abstract, array $parameters = []) {
        // Проверка циклических зависимостей
        if (isset($this->resolving[$abstract])) {
            throw new \RuntimeException("Circular dependency detected: " . implode(' -> ', array_keys($this->resolving)) . " -> $abstract");
        }

        $this->resolving[$abstract] = true;

        try {
            // Проверка синглтонов
            if (isset($this->singletons[$abstract]) && isset($this->instances[$abstract])) {
                unset($this->resolving[$abstract]);
                return $this->instances[$abstract];
            }

            $concrete = $this->bindings[$abstract] ?? $abstract;

            if (is_callable($concrete)) {
                $instance = $concrete($this, ...$parameters);
            } else {
                $instance = $this->buildInstance($concrete, $parameters);
            }

            // Сохранение синглтона
            if (isset($this->singletons[$abstract])) {
                $this->instances[$abstract] = $instance;
            }

            return $instance;
        } finally {
            unset($this->resolving[$abstract]);
        }
    }

    private function buildInstance(string $concrete, array $parameters) {
        $reflector = $this->getCachedReflection($concrete);
        $constructor = $reflector->getConstructor();

        if (!$constructor) {
            return new $concrete();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $this->resolveParameter($param, $parameters, $dependencies);
        }

        return $reflector->newInstanceArgs($dependencies);
    }

    private function resolveParameter(\ReflectionParameter $param, array $parameters, array &$dependencies) {
        $name = $param->getName();
        $type = $param->getType();

        if ($type && !$type->isBuiltin()) {
            $dependencies[] = $this->make($type->getName());
        } elseif (array_key_exists($name, $parameters)) {
            $dependencies[] = $parameters[$name];
        } elseif ($param->isDefaultValueAvailable()) {
            $dependencies[] = $param->getDefaultValue();
        } else {
            throw new \RuntimeException("Cannot resolve parameter: \${$name}");
        }
    }

    private function getCachedReflection(string $class): \ReflectionClass {
        if (!isset($this->reflectionCache[$class])) {
            if (!class_exists($class)) {
                throw new \RuntimeException("Class {$class} does not exist");
            }
            $this->reflectionCache[$class] = new \ReflectionClass($class);
        }
        return $this->reflectionCache[$class];
    }

    public function compile(string $cacheDir): void {
        // Генерация кэша рефлексии
        $cache = [];
        foreach ($this->bindings as $abstract => $concrete) {
            $cache[$abstract] = $this->getReflectionMetadata($concrete);
        }
        
        file_put_contents(
            $cacheDir.'/container_cache.php',
            '<?php return '.var_export($cache, true).';'
        );
    }

    private function getReflectionMetadata($concrete): array {
        if (!is_string($concrete)) return [];
        
        $reflector = $this->getCachedReflection($concrete);
        $metadata = [];
        
        if ($constructor = $reflector->getConstructor()) {
            foreach ($constructor->getParameters() as $param) {
                $metadata['parameters'][] = [
                    'name' => $param->getName(),
                    'type' => $param->getType() ? $param->getType()->getName() : null,
                    'builtin' => $param->getType() ? $param->getType()->isBuiltin() : false
                ];
            }
        }
        
        return $metadata;
    }
}
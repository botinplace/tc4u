<?php
namespace Core;

//use Psr\Container\ContainerInterface;
use RuntimeException;

//class Container implements ContainerInterface {
class Container {
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];
    private array $reflectionCache = [];
    private array $resolving = [];
    private array $compiledCache = [];
    protected bool $isCompiled = false;

    public function isCompiled(): bool {
        return $this->isCompiled;
    }

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
        if (isset($this->resolving[$abstract])) {
            throw new RuntimeException("Circular dependency: " 
                . implode(' → ', array_keys($this->resolving)) . " → $abstract");
        }

        $this->resolving[$abstract] = true;

        try {
            if ($this->isSingletonResolved($abstract)) {
                return $this->instances[$abstract];
            }

            $instance = $this->resolveInstance($abstract, $parameters);
            
            if ($this->isSingleton($abstract)) {
                $this->instances[$abstract] = $instance;
            }

            return $instance;
        } finally {
            unset($this->resolving[$abstract]);
        }
    }

    public function compile(string $cacheDir): void {
        $this->validateCompilation();
        $code = $this->generateContainerCode();
        $this->saveCompiledContainer($cacheDir, $code);
	$this->isCompiled = true;
    }

    private function isSingletonResolved(string $abstract): bool {
        return isset($this->singletons[$abstract]) 
            && isset($this->instances[$abstract]);
    }

    private function isSingleton(string $abstract): bool {
        return isset($this->singletons[$abstract]);
    }

    private function resolveInstance(string $abstract, array $parameters) {
        $concrete = $this->bindings[$abstract] ?? $abstract;

        if (is_callable($concrete)) {
            return $concrete($this, ...$parameters);
        }

        return $this->buildClassInstance($concrete, $parameters);
    }

    private function buildClassInstance(string $concrete, array $parameters) {
        $reflector = $this->getCachedReflection($concrete);
        
        if (!$constructor = $reflector->getConstructor()) {
            return new $concrete();
        }

        $dependencies = $this->resolveDependencies($constructor->getParameters(), $parameters);
        
        return $reflector->newInstanceArgs($dependencies);
    }

    private function resolveDependencies(array $parameters, array $userParams): array {
        $dependencies = [];
        
        foreach ($parameters as $param) {
            $dependencies[] = $this->resolveParameter($param, $userParams);
        }
        
        return $dependencies;
    }

    private function resolveParameter(\ReflectionParameter $param, array $userParams) {
        $name = $param->getName();
        $type = $param->getType();

        if ($type && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }

        return $userParams[$name] ?? $this->getDefaultValue($param);
    }

    private function getDefaultValue(\ReflectionParameter $param) {
        if (!$param->isDefaultValueAvailable()) {
            throw new RuntimeException("Parameter \${$param->getName()} is required");
        }
        
        return $param->getDefaultValue();
    }

    private function getCachedReflection(string $class): \ReflectionClass {
        if (!isset($this->reflectionCache[$class])) {
            $this->validateClassExists($class);
            $this->reflectionCache[$class] = new \ReflectionClass($class);
        }
        
        return $this->reflectionCache[$class];
    }

    private function validateClassExists(string $class): void {
        if (!class_exists($class) && !interface_exists($class)) {
            throw new RuntimeException("Class or interface {$class} not found");
        }
    }

    private function generateContainerCode(): string {
        $code = "<?php \n declare(strict_types=1);\n\n";
        $code .= "namespace MainApp\\cache;\n\n";
        $code .= "use Core\\Container;\n\n";
        $code .= "class CompiledContainer extends Container {\n";
        $code .= "    private \$compiledInstances = [];\n\n";

        foreach ($this->bindings as $abstract => $concrete) {
            $code .= $this->generateServiceMethod($abstract, $concrete);
        }

        $code .= "}\n";
        return $code;
    }

    private function generateServiceMethod(string $abstract, $concrete): string {
        $methodName = 'get_' . str_replace('\\', '__', $abstract);
        $isSingleton = isset($this->singletons[$abstract]);

        $code = "    public function {$methodName}() {\n";
        
        if ($isSingleton) {
            $code .= "        if (isset(\$this->compiledInstances['{$abstract}'])) {\n";
            $code .= "            return \$this->compiledInstances['{$abstract}'];\n";
            $code .= "        }\n";
        }

        if (is_callable($concrete)) {
            $code .= "        \$instance = call_user_func(\$this->bindings['{$abstract}'], \$this);\n";
        } else {
            $dependencies = $this->getCompiledDependencies($concrete);
            $code .= "        \$instance = new {$concrete}({$dependencies});\n";
        }

        if ($isSingleton) {
            $code .= "        \$this->compiledInstances['{$abstract}'] = \$instance;\n";
        }

        $code .= "        return \$instance;\n";
        $code .= "    }\n\n";
        
        return $code;
    }

    private function getCompiledDependencies(string $class): string {
        $reflector = $this->getCachedReflection($class);
        $dependencies = [];
        
        if ($constructor = $reflector->getConstructor()) {
            foreach ($constructor->getParameters() as $param) {
                $dependencies[] = $this->getParameterCode($param);
            }
        }
        
        return implode(', ', $dependencies);
    }

    private function getParameterCode(\ReflectionParameter $param): string {
        $type = $param->getType();
        
        if ($type && !$type->isBuiltin()) {
            return "\$this->get_{$this->normalizeClassName($type->getName())}()";
        }
        
        if ($param->isDefaultValueAvailable()) {
            return var_export($param->getDefaultValue(), true);
        }
        
        return 'null';
    }

    private function normalizeClassName(string $class): string {
        return str_replace('\\', '__', $class);
    }

    private function saveCompiledContainer(string $cacheDir, string $code): void {
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $filePath = "{$cacheDir}/CompiledContainer.php";
        file_put_contents($filePath, $code);

        if (!file_exists($filePath)) {
            throw new RuntimeException("Failed to compile container to {$filePath}");
        }
    }

    private function validateCompilation(): void {
        foreach ($this->bindings as $abstract => $concrete) {
            if (is_string($concrete)) {
                $this->validateClassExists($concrete);
            }
        }
    }
}
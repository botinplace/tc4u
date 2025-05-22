<?php
namespace Core;

//use Psr\Container\ContainerInterface;
//use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

//class Container implements ContainerInterface
class Container
{
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];
    private array $reflectionCache = [];
    private array $resolving = [];
    private array $tags = [];
    protected bool $isCompiled = false;
    private ?LoggerInterface $logger = null;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function isCompiled(): bool {
        return $this->isCompiled;
    }

    public function bind(string $abstract, $concrete = null, bool $singleton = false): void {
        $this->bindings[$abstract] = $concrete ?? $abstract;
        if ($singleton) {
            $this->singletons[$abstract] = true;
        }
    }

    public function singleton(string $abstract, $concrete = null): void {
        $this->bind($abstract, $concrete, true);
    }

    public function tag(array $services, string $tag): void {
        foreach ($services as $service) {
            $this->tags[$tag][] = $service;
        }
    }

    public function getTagged(string $tag): array {
        return array_map([$this, 'get'], $this->tags[$tag] ?? []);
    }

    public function get($id) {
        try {
            return $this->make($id);
        } catch (\Throwable $e) {
            $this->logError($e);
            throw $e;
        }
    }

    public function has($id): bool {
        return isset($this->bindings[$id]) || class_exists($id);
    }

    public function make(string $abstract, array $parameters = []) {
        if (isset($this->resolving[$abstract])) {
            $error = "Circular dependency: " . implode(' → ', array_keys($this->resolving)) . " → $abstract";
            $this->logError(new RuntimeException($error));
            throw new RuntimeException($error);
        }

        $this->resolving[$abstract] = true;

        try {
            if ($this->isSingleton($abstract)) {
                if (!isset($this->instances[$abstract])) {
                    $this->instances[$abstract] = $this->buildInstance($abstract, $parameters);
                }
                return $this->instances[$abstract];
            }

            return $this->buildInstance($abstract, $parameters);
        } finally {
            unset($this->resolving[$abstract]);
        }
    }

    private function buildInstance(string $abstract, array $parameters) {
        $concrete = $this->bindings[$abstract] ?? $abstract;

        if ($concrete instanceof \Closure || is_callable($concrete)) {
            $instance = $concrete($this, ...$parameters);
            $this->validateInstanceType($instance, $abstract);
            return $instance;
        }

        $reflector = $this->getReflection($concrete);
        $this->validateInstantiable($reflector);

        if (!$constructor = $reflector->getConstructor()) {
            return new $concrete();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $param) {
            $dependencies[] = $this->resolveDependency($param, $parameters);
        }

        $instance = $reflector->newInstanceArgs($dependencies);
        $this->validateInstanceType($instance, $abstract);

        return $instance;
    }

    private function validateInstanceType($instance, string $abstract): void {
        if (!$instance instanceof $abstract) {
            $error = "Resolved instance is not of type {$abstract}";
            $this->logError(new RuntimeException($error));
            throw new RuntimeException($error);
        }
    }

    private function getReflection(string $class): ReflectionClass {
        if (!isset($this->reflectionCache[$class])) {
            $this->validateClassExists($class);
            try {
                $this->reflectionCache[$class] = new ReflectionClass($class);
            } catch (ReflectionException $e) {
                $this->logError($e);
                throw new RuntimeException("Class {$class} not found");
            }
        }
        return $this->reflectionCache[$class];
    }

    private function validateClassExists(string $class): void {
        if (!class_exists($class) && !interface_exists($class)) {
            $error = "Class or interface {$class} not found";
            $this->logError(new RuntimeException($error));
            throw new RuntimeException($error);
        }
    }

    private function validateInstantiable(ReflectionClass $reflector): void {
        if (!$reflector->isInstantiable()) {
            $error = "Class {$reflector->name} is not instantiable";
            $this->logError(new RuntimeException($error));
            throw new RuntimeException($error);
        }
    }

    private function resolveDependency(\ReflectionParameter $param, array $parameters) {
        $name = $param->getName();
        $type = $param->getType();

        try {
            if ($type && !$type->isBuiltin()) {
                return $this->get($type->getName());
            }

            if (array_key_exists($name, $parameters)) {
                $this->validateParameterType($param, $parameters[$name]);
                return $parameters[$name];
            }

            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw new RuntimeException("Parameter \${$name} is required");
        } catch (\Throwable $e) {
            $this->logError($e);
            throw $e;
        }
    }

    private function validateParameterType(\ReflectionParameter $param, $value): void {
        $type = $param->getType();
        if (!$type || $type->isBuiltin()) {
            $typeName = $type ? $type->getName() : 'mixed';
            if ($type && !$this->isValidType($value, $typeName)) {
                $error = sprintf(
                    "Invalid type for parameter \$%s. Expected %s, got %s",
                    $param->getName(),
                    $typeName,
                    gettype($value)
                );
                $this->logError(new RuntimeException($error));
                throw new RuntimeException($error);
            }
        }
    }

    private function isValidType($value, string $type): bool {
        switch ($type) {
            case 'int':
                return is_int($value);
            case 'string':
                return is_string($value);
            case 'bool':
                return is_bool($value);
            case 'array':
                return is_array($value);
            case 'float':
                return is_float($value);
            case 'callable':
                return is_callable($value);
            case 'iterable':
                return is_iterable($value);
            case 'object':
                return is_object($value);
            default:
                return true; // For non-builtin types
        }
    }

    public function compile(string $cacheDir): void {
        if (file_exists("{$cacheDir}/CompiledContainer.php")) {
            return;
        }
        
        $this->validateCompilation();
        $code = $this->generateContainerCode();
        $this->saveCompiledContainer($cacheDir, $code);
        $this->isCompiled = true;
    }

    private function validateCompilation(): void {
        foreach ($this->bindings as $abstract => $concrete) {
            if (is_string($concrete)) {
                $this->validateClassExists($concrete);
            }
        }
    }

    private function logError(\Throwable $e): void {
        if ($this->logger) {
            $this->logger->error($e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    private function generateContainerCode(): string {
        $code = "<?php declare(strict_types=1);\n\n";
        $code .= "namespace MainApp\Cache;\n\n";
        $code .= "use Core\Container;\n\n";
        $code .= "class CompiledContainer extends Container {\n";
        $code .= "    private array \$compiledInstances = [];\n\n";

        foreach ($this->bindings as $abstract => $concrete) {
            $code .= $this->generateServiceMethod($abstract, $concrete);
        }

        $code .= "}\n";
        return $code;
    }

    private function generateServiceMethod(string $abstract, $concrete): string {
        $methodName = 'get' . str_replace('\\', '_', $abstract);
        $isSingleton = isset($this->singletons[$abstract]);

        $code = "    public function {$methodName}() {\n";
        
        if ($isSingleton) {
            $code .= "        if (isset(\$this->compiledInstances['{$abstract}'])) {\n";
            $code .= "            return \$this->compiledInstances['{$abstract}'];\n";
            $code .= "        }\n";
        }

        if ($concrete instanceof \Closure) {
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
        $reflector = $this->getReflection($class);
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
            return "\$this->get" . str_replace('\\', '_', $type->getName()) . "()";
        }
        
        if ($param->isDefaultValueAvailable()) {
            return var_export($param->getDefaultValue(), true);
        }
        
        return 'null';
    }

    private function saveCompiledContainer(string $cacheDir, string $code): void {
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $filePath = "{$cacheDir}/CompiledContainer.php";
        if (!file_put_contents($filePath, $code)) {
            throw new RuntimeException("Failed to compile container to {$filePath}");
        }
    }

    private function isSingleton(string $abstract): bool {
        return isset($this->singletons[$abstract]);
    }
}
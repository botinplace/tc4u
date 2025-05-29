<?php
namespace Core;

class TemplateEngine
{
    private $fast_array = [];
    private $loopStack = [];
    private array $fileTimeCache = [];
    private $compiledDir;
    private $templateCache = [];
    private $loopVarCounter = 0;
    private bool $debugMode = false;
    private int $maxCacheSize = 1000; // Максимальное количество кешированных файлов
    private int $maxRecursionDepth = 10; // Максимальная глубина рекурсии для объектов

    public function __construct(array $fast_array = [], bool $debugMode = false)
    {
        $this->debugMode = $debugMode;
        $this->fast_array = $this->prepareExtraVars($fast_array);
        
        // Убедимся, что путь к кешу корректен
        $this->compiledDir = rtrim(APP_DIR, '/') . '/cache/templates/';
        $this->ensureCacheDirectory();
    }

    private function ensureCacheDirectory(): void
    {
        // Создаем директорию, если она не существует
        if (!is_dir($this->compiledDir)) {
            if (!mkdir($this->compiledDir, 0775, true)) {
                throw new \RuntimeException("Failed to create cache directory: " . $this->compiledDir);
            }
        }

        // Проверяем доступность директории для записи
        if (!is_writable($this->compiledDir)) {
            throw new \RuntimeException("Cache directory is not writable: " . $this->compiledDir);
        }
    }
    
    private function prepareExtraVars(array $extra_vars): array
    {
        $fast_array = [];
        foreach ($extra_vars as $key => $value) {
            // Разрешаем передачу объектов только в debug режиме
            if (is_object($value)) {
                if ($this->debugMode) {
                    $fast_array["{{" . $key . "}}"] = $value;
                } else {
                    trigger_error("Objects not allowed in production", E_USER_WARNING);
                    $fast_array["{{" . $key . "}}"] = "Object";
                }
            } else {
                $fast_array["{{" . $key . "}}"] = is_scalar($value)
                    ? htmlspecialchars($value, ENT_QUOTES, "UTF-8")
                    : $value;
            }
        }
        return $fast_array;
    }

    public function render(string $template, array $data = []): string
    {
        try {
            $this->fast_array = array_merge($this->fast_array, $this->prepareExtraVars($data));
            $compiledFile = $this->compileTemplate($template);
            return $this->renderCompiled($compiledFile);
        } catch (\Throwable $e) {
            $errorMsg = "Template error: " . $e->getMessage();
            error_log($errorMsg);
            
            if ($this->debugMode) {
                return "<!-- ERROR: " . htmlspecialchars($errorMsg) . " -->";
            }
            
            return "<!-- Template rendering error -->";
        }
    }

   private function compileTemplate(string $template): string
    {
        $hash = md5($template);
        $cacheFile = $this->compiledDir . $hash . '.php';
        
        // Проверяем необходимость компиляции
        $needsCompile = $this->debugMode || !file_exists($cacheFile);
        
        // Для существующих файлов проверяем актуальность
        if (!$needsCompile && !$this->debugMode) {
            $sourceMTime = $this->getTemplateMTime($template);
            $cacheMTime = filemtime($cacheFile);
            $needsCompile = $sourceMTime > $cacheMTime;
        }
        
        if ($needsCompile) {
            $compiled = $this->compile($template);
            $this->atomicWrite($cacheFile, $compiled);
        }
        
        return $cacheFile;
    }
    
    private function getTemplateMTime(string $template): int
    {
        // В реальной реализации нужно определить время модификации шаблона
        return time(); // Заглушка
    }
    
    private function atomicWrite(string $filePath, string $content): void
    {
        // Создаем временный файл В ТОЙ ЖЕ директории
        $tempFile = $this->compiledDir . uniqid('tpl_', true);
        
        // Записываем содержимое во временный файл
        if (file_put_contents($tempFile, $content) === false) {
            throw new \RuntimeException("Failed to write to temp file: $tempFile");
        }
        
        // Атомарное переименование
        if (!rename($tempFile, $filePath)) {
            @unlink($tempFile);
            throw new \RuntimeException("Failed to rename temp file to: $filePath");
        }
        
        // Устанавливаем корректные права
        @chmod($filePath, 0664);
    }
    
    private function logError(string $message): void
    {
        error_log("[TemplateEngine] " . $message);
        if ($this->debugMode) {
            // Можно добавить вывод в лог или на экран в режиме отладки
        }
    }
    
    // Автоматическая очистка кеша
    private function cleanupCache(): void
    {
        if ($this->debugMode) return;
        
        $files = glob($this->compiledDir . '*.php');
        if (count($files) > $this->maxCacheSize) {
            // Сортируем по времени модификации
            usort($files, function($a, $b) {
                return filemtime($a) > filemtime($b);
            });
            
            // Удаляем самые старые файлы
            $toDelete = count($files) - $this->maxCacheSize;
            for ($i = 0; $i < $toDelete; $i++) {
                @unlink($files[$i]);
            }
        }
    }

    private function compile(string $template): string
    {
        $this->loopVarCounter = 0;
        
        // Шаг 1: Замена экранированных тегов
        $template = preg_replace_callback(
            '/\\\\(\{\{|\{%|%\\})/',
            function ($matches) {
                $markers = [
                    '{{' => '__ESCAPED_DOUBLE__',
                    '{%' => '__ESCAPED_BLOCK_START__',
                    '%}' => '__ESCAPED_BLOCK_END__'
                ];
                return $markers[$matches[1]] ?? $matches[0];
            },
            $template
        );
        
        $passes = 3;
        do {
            $previous = $template;
            $template = $this->compileForeach($template);
            $template = $this->compilePlaceholders($template);
            $template = $this->compileIfConditions($template);
        } while ($passes-- > 0 && $template !== $previous);
        
        // Шаг 2: Восстановление экранированных тегов
        $template = preg_replace_callback(
            '/__(ESCAPED_[A-Z_]+)__/',
            function ($matches) {
                $reverseMap = [
                    'ESCAPED_DOUBLE' => '{{',
                    'ESCAPED_BLOCK_START' => '{%',
                    'ESCAPED_BLOCK_END' => '%}'
                ];
                return $reverseMap[$matches[1]] ?? $matches[0];
            },
            $template
        );
        
        return $template;
    }

    private function compileForeach(string $template): string
    {
        $pattern = '/{%\s*foreach\s+([a-zA-Z0-9-_.]+)\s*%}(.*?){%\s*endforeach\s*%}/s';
        return preg_replace_callback(
            $pattern,
            function ($matches) {
                $var = trim($matches[1]);
                $content = $this->compile($matches[2]);
                $varAccess = $this->compileVariableAccess($var);
                
                $this->loopVarCounter++;
                $keyVar = "key_{$this->loopVarCounter}";
                $valueVar = "value_{$this->loopVarCounter}";

                return <<<PHP
<?php
\$parentLoopContext = end(\$this->loopStack) ?: null;
\$loopData = {$varAccess} ?? [];
foreach (\$loopData as \${$keyVar} => \${$valueVar}) {
    \$currentLoopContext = [
        'key' => \${$keyVar},
        'value' => \${$valueVar},
        'parent' => \$parentLoopContext
    ];
    array_push(\$this->loopStack, \$currentLoopContext);
    ?>
    {$content}
    <?php
    array_pop(\$this->loopStack);
}
?>
PHP;
            },
            $template
        );
    }

    private function compileVariableAccess(string $var): string
    {
        // Обработка констант
        if (is_numeric($var)) {
            return $var;
        }
        
        // Обработка строковых литералов
        if (preg_match('/^([\'"])(.*)\1$/', $var, $matches)) {
            return var_export($matches[2], true);
        }
        
        // Приоритет: переменные цикла
        if (!empty($this->loopStack)) {
            if ($var === 'key') {
                return "\$this->getCurrentLoopContext('key')";
            }
            
            if ($var === 'value') {
                return "\$this->getCurrentLoopContext('value')";
            }
            
            // Обработка value.property
            if (strpos($var, 'value.') === 0) {
                $property = substr($var, 6);
                return "\$this->getNestedValue(\$this->getCurrentLoopContext('value'), '$property')";
            }
            
            // Обработка parent.value
            if (strpos($var, 'parent.') === 0) {
                $levels = substr_count($var, 'parent.');
                $property = substr($var, strrpos($var, '.') + 1);
                return "\$this->getValueFromParentLoop('{$property}', {$levels})";
            }
        }
        
        return "\$this->getValue('$var')";
    }
    
    
    public function getValue(string $key)
    {
        // Приоритет: переменные цикла
        if (!empty($this->loopStack)) {
            if ($key === 'key') {
                return $this->getCurrentLoopContext('key');
            }
            
            if ($key === 'value') {
                return $this->getCurrentLoopContext('value');
            }
            
            if (strpos($key, 'value.') === 0) {
                $property = substr($key, 6);
                return $this->getNestedValue(
                    $this->getCurrentLoopContext('value'), 
                    $property
                );
            }
            
            if (strpos($key, 'parent.') === 0) {
                $levels = substr_count($key, 'parent.');
                $property = substr($key, strrpos($key, '.') + 1);
                return $this->getValueFromParentLoop($property, $levels);
            }
        }

        // Обработка глобальных переменных
        $keys = explode('.', $key);
        $current = $this->fast_array;
        
        // Основное исправление: используем обёрнутый ключ для первого уровня
        $firstKey = array_shift($keys);
        $wrappedKey = "{{" . $firstKey . "}}";
        
        if (!isset($current[$wrappedKey])) {
            return null;
        }
        
        $current = $current[$wrappedKey];
        
        // Обрабатываем вложенные свойства
        foreach ($keys as $k) {
            if (is_array($current)) {
                if (isset($current[$k])) {
                    $current = $current[$k];
                } 
                elseif (is_numeric($k) && isset($current[(int)$k])) {
                    $current = $current[(int)$k];
                } else {
                    return null;
                }
            }
            elseif (is_object($current)) {
                if (property_exists($current, $k)) {
                    $current = $current->$k;
                } else {
                    return null;
                }
            }
            else {
                return null;
            }
        }
        
        return $current;
    }

    public function getCurrentLoopContext(string $key)
    {
        if (empty($this->loopStack)) {
            return null;
        }
        $context = end($this->loopStack);
        return $context[$key] ?? null;
    }

    public function getValueFromParentLoop(string $property)
    {
        $stackSize = count($this->loopStack);
        if ($stackSize > 1) {
            $context = $this->loopStack[$stackSize - 2]; // Берем родительский контекст
            return $this->getNestedValue($context['value'], $property);
        }
        return null;
    }


    private function compilePlaceholders(string $template): string
    {
        return preg_replace_callback(
            '/(?<!\\\\){{(\s*[a-zA-Z0-9-_.()\/"\'\\\\\s\+\-\*\%]+)(?:\s*\|\s*([a-zA-Z0-9]+))?\s*}}/',
            function ($matches) {
                $var = trim($matches[1]);
                $filter = $matches[2] ?? null;
                
                // Пропускаем экранированные конструкции
                if (strpos($var, 'ESCAPED_') === 0) {
                    return '{{' . $matches[1] . '}}';
                }
                
                // Обработка строковых литералов
                if (preg_match('/^([\'"])(.*)\1$/', $var, $stringMatches)) {
                    $stringValue = $stringMatches[2];
                    return "<?php echo \$this->applyFilter('$stringValue', " . ($filter ? "'$filter'" : 'null') . "); ?>";
                }
                
                // Обработка выражений
                if (preg_match('/[+\-*\/%]/', $var)) {
                    $expression = '';
                    $tokens = preg_split('/([+\-*\/%()])/', $var, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                    
                    foreach ($tokens as $token) {
                        $token = trim($token);
                        if (empty($token)) continue;
                        
                        if (is_numeric($token) || in_array($token, ['+', '-', '*', '/', '%', '(', ')'])) {
                            $expression .= $token;
                        } else {
                            $expression .= $this->compileVariableAccess($token);
                        }
                    }
                    
                    $varCode = "($expression)";
                    
                } else {
                    $varCode = $this->compileVariableAccess($var);
                }
                
                return "<?php echo \$this->applyFilter($varCode, " . ($filter ? "'$filter'" : 'null') . "); ?>";
            },
            $template
        );
    }
                        
/* OLD
    private function compilePlaceholders(string $template): string
    {
        return preg_replace_callback(
            '/(?<!\\\\){{(\s*[a-zA-Z0-9-_.()\/"\'\\\\\s\+\-\*\%]+)(?:\s*\|\s*([a-zA-Z0-9]+))?\s*}}/',
            function ($matches) {
                $var = trim($matches[1]);
                $filter = $matches[2] ?? null;
                
                // Пропускаем экранированные конструкции
                if (strpos($var, 'ESCAPED_') === 0) {
                    return '{{' . $matches[1] . '}}';
                }
                
                if (preg_match('/[+\-*\/%]/', $var)) {
                    $expression = '';
                    $tokens = preg_split('/([+\-*\/%()])/', $var, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
                    
                    foreach ($tokens as $token) {
                        $token = trim($token);
                        if (empty($token)) continue;
                        
                        if (is_numeric($token) || in_array($token, ['+', '-', '*', '/', '%', '(', ')'])) {
                            $expression .= $token;
                        } else {
                            $expression .= $this->compileVariableAccess($token);
                        }
                    }
                    
                    $varCode = "($expression)";
                } else {
                    $varCode = $this->compileVariableAccess($var);
                }
                
                return "<?php echo \$this->applyFilter($varCode, " . ($filter ? "'$filter'" : 'null') . "); ?>";
            },
            $template
        );
    }
*/ 
    private function compileIfConditions(string $template): string
    {
        $pattern = '/{%\s*if\s+(?<condition>.+?)\s*%}(?<if_content>.*?)(?:{%\s*else\s*%}(?<else_content>.*?))?{%\s*endif\s*%}/s';
        
        return preg_replace_callback(
            $pattern,
            function ($match) {
                $condition = trim($match['condition']);
                $ifContent = $this->compile($match['if_content']);
                $elseContent = isset($match['else_content']) 
                    ? $this->compile($match['else_content']) 
                    : '';

                $conditionCode = $this->parseCondition($condition);

                return "<?php if ({$conditionCode}): ?>\n" 
                     . $ifContent 
                     . ($elseContent ? "<?php else: ?>\n" . $elseContent : "") 
                     . "<?php endif; ?>";
            },
            $template
        );
    }

    private function parseCondition(string $condition): string
{
    $condition = preg_replace('/\bnot\s+/', '!', $condition);
    
    // Обработка числовых литералов
    if (is_numeric($condition)) {
        return $condition;
    }
    
    // Обработка строковых литералов
    if (preg_match('/^([\'"])(.*)\1$/', $condition, $matches)) {
        return var_export($matches[2], true);
    }
    
    // Булевые значения
    $lower = strtolower($condition);
    if ($lower === 'true') return 'true';
    if ($lower === 'false') return 'false';
    if ($lower === 'null') return 'null';
    
    // Специальная обработка для проверки на пустоту
    if (preg_match('/^!\s*([a-zA-Z0-9-_.]+)$/', $condition, $matches)) {
        $var = $this->compileVariableAccess($matches[1]);
        return "(empty($var) || (is_array($var) && count($var) === 0))";
    }
    
    // Сравнения
    if (preg_match('/(.+?)\s*(===|!==|==|!=|>=|<=|>|<)\s*(.+)/', $condition, $matches)) {
        $left = $this->parseConditionPart(trim($matches[1]));
        $operator = $matches[2];
        $right = $this->parseConditionPart(trim($matches[3]));
        return "({$left} {$operator} {$right})";
    }
    
    // Логические операторы
    if (preg_match('/(.+?)\s*(&&|\|\|)\s*(.+)/', $condition, $matches)) {
        $left = $this->parseConditionPart(trim($matches[1]));
        $operator = $matches[2];
        $right = $this->parseConditionPart(trim($matches[3]));
        return "({$left} {$operator} {$right})";
    }
    
    // Отрицания для составных выражений
    if (preg_match('/^!\s*(\w+)/', $condition, $matches)) {
        $var = $this->parseConditionPart($matches[1]);
        return "(!{$var})";
    }
    
    // Проверка на пустоту для неотрицательных случаев
    if (preg_match('/^[a-zA-Z0-9-_.]+$/', $condition)) {
        $var = $this->compileVariableAccess($condition);
        return "(!empty($var) && (!is_array($var) || count($var) > 0))";
    }
    
    return $this->parseConditionPart($condition);
}    
    private function parseConditionPart(string $part): string
    {
        // Если часть является выражением (содержит операторы)
        if (preg_match('/[+\-*\/%()]/', $part)) {
            $expression = '';
            $tokens = preg_split('/([+\-*\/%()])/', $part, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            
            foreach ($tokens as $token) {
                $token = trim($token);
                if (empty($token)) continue;
                
                if (is_numeric($token) || in_array($token, ['+', '-', '*', '/', '%', '(', ')'])) {
                    $expression .= $token;
                } else {
                    $expression .= $this->compileVariableAccess($token);
                }
            }
            
            return "($expression)";
        }
        
        return $this->compileVariableAccess($part);
    }

    private function renderCompiled(string $compiledFile): string
    {
        if (!file_exists($compiledFile)) {
            throw new \RuntimeException("Compiled template not found: $compiledFile");
        }
        
        $_tmpVar = $this->fast_array;
        ob_start();
        include $compiledFile;
        $output = ob_get_clean();
        $this->loopStack = [];
        return $output;
    }

    private function getValueFromFastArray(string $key, array $fast_array)
    {
        // Приоритет: переменные цикла
        if (!empty($this->loopStack)) {
            if ($key === 'key') {
                return $this->getCurrentLoopContext('key');
            }
            
            if ($key === 'value') {
                return $this->getCurrentLoopContext('value');
            }
            
            if (strpos($key, 'value.') === 0) {
                $property = substr($key, 6);
                return $this->getNestedValue(
                    $this->getCurrentLoopContext('value'), 
                    $property
                );
            }
            
            if (strpos($key, 'parent.') === 0) {
                $property = substr($key, 7);
                return $this->getValueFromParentLoop($property);
            }
        }

        // Обработка глобальных переменных
        $keys = explode('.', $key);
        $current = $fast_array;
        
        foreach ($keys as $index => $k) {
            if ($index === 0) {
                $wrappedKey = "{{" . $k . "}}";
                if (isset($current[$wrappedKey])) {
                    $current = $current[$wrappedKey];
                    continue;
                }
            }
            
            if (isset($current[$k])) {
                $current = $current[$k];
            }
            elseif (is_array($current) && is_numeric($k) && isset($current[(int)$k])) {
                $current = $current[(int)$k];
            } else {
                return null;
            }
        }
        
        return $current;
    }
 
    private function getNestedValue($data, $path)
    {
        if ($depth > $this->maxRecursionDepth) {
            trigger_error("Max recursion depth exceeded", E_USER_WARNING);
            return null;
        }
        
        if (empty($path)) return $data;
        
        $keys = explode('.', $path);
        
        foreach ($keys as $key) {
            if (is_array($data)) {
                $wrappedKey = "{{" . $key . "}}";
                
                if (isset($data[$wrappedKey])) {
                    $data = $data[$wrappedKey];
                } 
                elseif (isset($data[$key])) {
                    $data = $data[$key];
                } 
                elseif (is_numeric($key) && isset($data[(int)$key])) {
                    $data = $data[(int)$key];
                } else {
                    return null;
                }
            }
            elseif (is_object($data)) {
                
                $className = get_class($data);
                if (in_array($className, ['PDO', 'mysqli'])) {
                    trigger_error("Access to database objects is forbidden", E_USER_WARNING);
                    return null;
                }
                
                if (property_exists($data, $key)) {
                    $reflection = new \ReflectionProperty($data, $key);
                    if ($reflection->isPublic()) {
                        $data = $data->$key;
                    } else {
                        return null;
                    }
                } else {
                    return null;
                }
                
            }
            else {
                return null;
            }
            
            $depth++;
            if ($depth > $this->maxRecursionDepth) {
                trigger_error("Max recursion depth exceeded", E_USER_WARNING);
                return null;
            }
            
        }
        
        return $data;
    }

    public function clearCache(int $maxAge = 86400): void
    {
        $files = glob($this->compiledDir . '*.php');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $fileAge = $now - filemtime($file);
                if ($fileAge > $maxAge) {
                    @unlink($file);
                }
            }
        }
    }
    
    public function applyFilter($value, ?string $filter = null)
    {
        $valueType = gettype($value);
        if ($valueType === 'array' || $valueType === 'object') {
            $result = print_r($value, true);
            return $filter === "html" ? $result : htmlspecialchars($result, ENT_QUOTES, "UTF-8");
        }

        if ($value === null) {
            return '';
        }

        if ($valueType === 'boolean') {
            return $value ? 'true' : 'false';
        }

        if ($filter === "filetime") {
            return $this->applyFileTimeFilter($value);
        }
        
        if ($filter === "html") {
            return html_entity_decode($value);
        }
        
        return htmlspecialchars($value ?? '', ENT_QUOTES, "UTF-8");
    }

    private function applyFileTimeFilter(string $path): string
    {
        if (isset($this->fileTimeCache[$path])) {
            return $this->fileTimeCache[$path];
        }
    
        $normalizedPath = ltrim(str_replace(['../', '..\\'], '', $path), '/');
        $absolutePath = PUBLIC_DIR . '/' . $normalizedPath;
    
        if (!file_exists($absolutePath)) {
            $this->fileTimeCache[$path] = $path;
            return $path;
        }
    
        $result = $path . '?v=' . filemtime($absolutePath);
        $this->fileTimeCache[$path] = $result;
        return $result;
    }
}
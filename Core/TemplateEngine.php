<?php
namespace Core;

class TemplateEngine
{
    private $data = [];
    private $contextStack = [];

    public function __construct(array $data = [])
    {
        $this->data = $this->prepareData($data);
    }

    private function pushContext(array $context): void
    {
        array_push($this->contextStack, $context);
    }
    
    private function popContext(): array
    {
        return array_pop($this->contextStack);
    }
    
    private function getCurrentContext(): array
    {
        return end($this->contextStack) ?: [];
    }
    
    private function prepareData(array $data): array
    {
        $prepared = [];
        foreach ($data as $key => $value) {
            $prepared[$key] = is_scalar($value) 
                ? htmlspecialchars($value, ENT_QUOTES, "UTF-8")
                : (is_array($value) ? $value : (is_object($value) ? "Object" : ""));
        }
        return $prepared;
    }

    public function render(string $template, array $data = []): string
    {
        $this->data = array_merge($this->data, $this->prepareData($data));
        $output = $this->processLoops($template);
        $output = $this->processPlaceholders($output);
        $output = $this->processConditions($output);
        return $output;
    }

    private function processPlaceholders(string $template): string
    {
        return preg_replace_callback(
            '/\\\\?{{\s*([a-zA-Z0-9\-_.|]+)\s*}}/',
            function ($matches) {
                if (strpos($matches[0], '\\') === 0) {
                    return substr($matches[0], 1);
                }
                
                $parts = explode('|', $matches[1]);
                $key = trim($parts[0]);
                $filter = $parts[1] ?? null;
                
                $value = $this->getValue($key);
                
                if ($value === null) {
                    return $matches[0];
                }
                
                if (is_array($value) || is_object($value)) {
                    return is_array($value) ? 'Array' : 'Object';
                }
                
                if ($filter !== 'html') {
                    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
                
                return $value;
            },
            $template
        );
    }

    private function processLoops(string $template): string
{
    $pattern = '/\\\\?{%\s*foreach\s+([a-zA-Z0-9\-_.]+)\s*%}(.*?){%\s*endforeach\s*%}/s';
    
    while (preg_match($pattern, $template)) {
        $template = preg_replace_callback(
            $pattern,
            function ($matches) {
                if (strpos($matches[0], '\\') === 0) {
                    return substr($matches[0], 1);
                }
                
                $items = $this->getValue($matches[1]);
                if (!is_array($items)) {
                    return '';
                }
                
                $output = '';
                foreach ($items as $key => $item) {
                    $context = [
                        'key' => $key,
                        'item' => $item,
                        'parent' => $this->getCurrentContext()
                    ];
                    
                    $this->pushContext($context);
                    $processedContent = $this->processBlockContent($matches[2]);
                    $this->popContext();
                    
                    $output .= $processedContent;
                }
                
                return $output;
            },
            $template
        );
    }
    
    return $template;
}

private function getValue(string $key)
{
    // Сначала проверяем в текущем контексте
    $context = $this->getCurrentContext();
    $value = $this->resolveFromContext($key, $context);
    
    if ($value !== null) {
        return $value;
    }
    
    // Если не найдено в контексте, проверяем в глобальных данных
    $value = $this->resolveFromData($key, $this->data);
    
    // Если это корневой массив (например, для {% foreach _ %})
    if ($value === null && $key === '_' && empty($this->contextStack)) {
        return $this->data;
    }
    
    return $value;
}

private function resolveFromContext(string $key, array $context)
{
    if (empty($context)) {
        return null;
    }
    
    // Специальная обработка для item.property
    if (strpos($key, 'item.') === 0) {
        $property = substr($key, 5);
        if (isset($context['item']) && is_array($context['item'])) {
            return $this->resolveFromData($property, $context['item']);
        }
        return null;
    }
    
    $parts = explode('.', $key);
    $value = $context;
    
    foreach ($parts as $part) {
        if (isset($value[$part])) {
            $value = $value[$part];
        } elseif (isset($value['item']) && is_array($value['item']) && isset($value['item'][$part])) {
            $value = $value['item'][$part];
        } else {
            return null;
        }
    }
    
    return $value;
}
    
    private function processBlockContent(string $content): string
    {
        // Обрабатываем вложенные плейсхолдеры
        $content = $this->processNestedPlaceholders($content);
        
        // Обрабатываем вложенные циклы
        $content = $this->processLoops($content);
        
        // Обрабатываем условия
        $content = $this->processConditions($content);
        
        return $content;
    }

    private function processNestedPlaceholders(string $content): string
    {
        return preg_replace_callback(
            '/{{\s*((?:parent\.)*)(key|item)(?:\.([a-zA-Z0-9\-_.]+))?\s*}}/',
            function ($matches) {
                $levels = substr_count($matches[1], 'parent.');
                $var = $matches[2];
                $property = $matches[3] ?? null;
                
                $context = $this->getCurrentContext();
                
                // Поднимаемся по родительским контекстам
                for ($i = 0; $i < $levels; $i++) {
                    if (isset($context['parent'])) {
                        $context = $context['parent'];
                    } else {
                        return '';
                    }
                }
                
                $value = $context[$var] ?? null;
                
                // Обрабатываем вложенные свойства (item.property.subproperty)
                if ($property && is_array($value)) {
                    $parts = explode('.', $property);
                    foreach ($parts as $part) {
                        if (isset($value[$part])) {
                            $value = $value[$part];
                        } else {
                            return '';
                        }
                    }
                }
                
                // Возвращаем значение с экранированием HTML
                if (is_scalar($value)) {
                    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
                
                return '';
            },
            $content
        );
    }

    private function processConditions(string $template): string
    {
        return preg_replace_callback(
            '/\\\\?{%\s*if\s+([a-zA-Z0-9\-_.]+)\s*(==|!=|>=|<=|>|<)\s*([^%]+)\s*%}(.*?){%\s*endif\s*%}/s',
            function ($matches) {
                if (strpos($matches[0], '\\') === 0) {
                    return substr($matches[0], 1);
                }
                
                $left = $this->getValue(trim($matches[1]));
                $operator = trim($matches[2]);
                $right = $this->getValue(trim($matches[3]));
                
                $result = false;
                switch ($operator) {
                    case '==': $result = $left == $right; break;
                    case '!=': $result = $left != $right; break;
                    case '>=': $result = $left >= $right; break;
                    case '<=': $result = $left <= $right; break;
                    case '>': $result = $left > $right; break;
                    case '<': $result = $left < $right; break;
                }
                
                return $result ? $matches[4] : '';
            },
            $template
        );
    }

    private function getValue(string $key)
        {
            // Сначала проверяем в текущем контексте
            $context = $this->getCurrentContext();
            $value = $this->resolveFromContext($key, $context);
            
            if ($value !== null) {
                return $value;
            }
            
            // Если не найдено в контексте, проверяем в глобальных данных
            $value = $this->resolveFromData($key, $this->data);
            
            // Если не найдено и там, возможно это числовой массив (корневой)
            if ( $value === null && empty($this->contextStack) ) {
                return $this->data;
            }
            
            return $value;
        }
    
    private function resolveFromContext(string $key, array $context)
    {
        if (empty($context)) {
            return null;
        }
        
        $parts = explode('.', $key);
        $value = $context;
        
        foreach ($parts as $part) {
            if (isset($value[$part])) {
                $value = $value[$part];
            } elseif (isset($value['item']) && is_array($value['item']) && isset($value['item'][$part])) {
                $value = $value['item'][$part];
            } else {
                return null;
            }
        }
        
        return $value;
    }
    
    private function resolveFromData(string $key, array $data)
    {
        $parts = explode('.', $key);
        $value = $data;
        
        foreach ($parts as $part) {
            if (isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }
        
        return $value;
    }
}

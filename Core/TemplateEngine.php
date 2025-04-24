<?php
namespace Core;

class TemplateEngine
{
    private $fast_array = [];
    private $contextStack = [];

    public function __construct(array $fast_array = [])
    {
        $this->fast_array = $this->prepareExtraVars($fast_array);
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
    
    private function prepareExtraVars(array $extra_vars): array
    {
        $fast_array = [];
        foreach ($extra_vars as $key => $value) {
            $fast_array["{{" . $key . "}}"] = is_scalar($value)
                ? htmlspecialchars($value, ENT_QUOTES, "UTF-8")
                : (is_array($value)
                    ? $value
                    : (is_object($value)
                        ? "Object"
                        : ""));
        }
        return $fast_array;
    }

    public function render(string $template, array $data = []): string
    {
        $this->fast_array = array_merge($this->fast_array, $this->prepareExtraVars($data));
        $output = $this->replaceForeachLoop($template);
        $output = $this->replacePlaceholders($output);
        $output = $this->processIfConditions($output);
        return $output;
    }

    private function replacePlaceholders(string $output): string
    {
        return preg_replace_callback(
            "/\\\\?{\\{?\s*([a-zA-Z0-9-_.]*)\s*[|]?\s*([a-zA-Z0-9]*)\s*\\}?\\}/sm",
            function ($matches) {
                if (strpos($matches[0], '\\') === 0) {
                    return "{{" . $matches[1] . "}}";
                }
                return $this->resolvePlaceholder($matches);
            },
            $output
        );
    }

    private function replaceForeachLoop(string $output): string
    {
        return preg_replace_callback(
            "/\\\\?{%\s*foreach\s+([a-zA-Z0-9-_.]*)\s*%}((?:(?R)|.*?)*){%\s*endforeach\s*%}/sm",
            function ($matches) {
                if (strpos($matches[0], '\\') === 0) {
                    return ltrim($matches[0], '\\');
                }
                
                $currentContext = $this->getCurrentContext();
                $value = $this->getValueForForeach($matches[1], $currentContext);
                
                
                
                if (!is_array($value)) {
                    return "";
                }
                
                
                $output = "";
                foreach ($value as $key => $item) {
                  
                  
                    $context = [
                        'key' => $key,
                        'value' => $item,
                        'parent' => $currentContext
                    ];
                  
                    $this->pushContext($context);
                    $output .= $this->processLoopContent($matches[2]);
                    $this->popContext();
                }
                
                return $output;
            },
            $output
        );
    }

    private function processLoopContent(string $content): string
    {
        $content = $this->replaceLoopPlaceholders($content);
        $content = $this->processIfConditions($content);
        $content = $this->replaceForeachLoop($content);
        return $content;
    }

    private function replaceLoopPlaceholders(string $content): string
{
    return preg_replace_callback(
        "/{{\s*((?:parent\.)*)(key|value)(?:\.([a-zA-Z0-9_.-]+))?\s*}}/sm",
        function ($matches) {
            $parentLevels = substr_count($matches[1], 'parent.');
            $var = $matches[2];
            $propertyPath = $matches[3] ?? null;

            $currentContext = $this->getCurrentContext();
            

            for ($i = 0; $i < $parentLevels; $i++) {
                if (isset($currentContext['parent'])) {
                    $currentContext = $currentContext['parent'];
                } else {
                    return ''; 
                }
            }


            $value = $currentContext[$var] ?? null;

            if ($value === null) {
                return '';
            }


            if ($propertyPath) {
                $keys = explode('.', $propertyPath);
                foreach ($keys as $key) {
                    if (is_array($value) && isset($value[$key])) {
                        $value = $value[$key];
                    } elseif (is_object($value) && isset($value->$key)) {
                        $value = $value->$key;
                    } else {
                        return '';
                    }
                }
                return is_scalar($value) ? htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8") : '';
            }


            if ($var === 'key') {
                return htmlspecialchars((string)$currentContext['key'], ENT_QUOTES, "UTF-8");
            }
            
            if ($var === 'value') {
                if (is_scalar($currentContext['value'])) {
                    return htmlspecialchars((string)$currentContext['value'], ENT_QUOTES, "UTF-8");
                }
                return '';
            }

            return '';
        },
        $content
    );
}

    private function getValueForForeach(string $key, array $context)
{

    if (strpos($key, '.') !== false) {
        $parts = explode('.', $key);
        $value = $context;
        
        foreach ($parts as $part) {
            if (isset($value[$part])) {
                $value = $value[$part];
            } elseif (isset($value['value']) && is_array($value['value']) && isset($value['value'][$part])) {
                $value = $value['value'][$part];
            } else {
                if (isset($context['parent'])) {
                    return $this->getValueForForeach($key, $context['parent']);
                }
                return null;
            }
        }
        return $value;
    }
    

    if (isset($context[$key])) {
        return $context[$key];
    }
    

    if (isset($context['value']) && is_array($context['value'])) {
        if (isset($context['value'][$key])) {
            return $context['value'][$key];
        }
    }
    

    if (isset($context['parent'])) {
        return $this->getValueForForeach($key, $context['parent']);
    }
    

    return $this->getValueFromFastArray($key);
}
    
    private function processIfConditions(string $content): string
    {
        return preg_replace_callback(
            "/\\\\?{%\s*if\s+([^ ]+)\s*(==|!=)\s*([^ ]+)\s*%}(.*?){%\s*endif\s*%}/sm",
            function ($matches) {
                $currentContext = $this->getCurrentContext();
                $leftValue = $this->getComparisonValue($matches[1], $currentContext);
                $operator = trim($matches[2]);
                $rightValue = $this->getComparisonValue($matches[3], $currentContext);

                if (
                    ($operator === "==" && $leftValue == $rightValue) ||
                    ($operator === "!=" && $leftValue != $rightValue)
                ) {
                    return $matches[4];
                }
                return "";
            },
            $content
        );
    }

    private function getComparisonValue($variable, array $context)
    {
        if (isset($context[$variable])) {
            return $context[$variable];
        }

        if (strpos($variable, '.') !== false) {
            $parts = explode('.', $variable);
            if ($parts[0] === 'value' && isset($context['value'])) {
                $value = $context['value'];
                foreach (array_slice($parts, 1) as $part) {
                    if (is_array($value) && isset($value[$part])) {
                        $value = $value[$part];
                    } else {
                        return null;
                    }
                }
                return $value;
            }
        }

        return $this->getValueFromFastArray($variable);
    }

    private function getValueFromFastArray(string $key)
    {
        $currentContext = $this->getCurrentContext();
        if ($currentContext) {
            $contextValue = $this->getValueForForeach($key, $currentContext);
            if ($contextValue !== null) {
                return $contextValue;
            }
        }
        
        $keys = explode('.', $key);
        $value = $this->fast_array;

        foreach ($keys as $k) {
            if (isset($value["{{" . $k . "}}"])) {
                $value = $value["{{" . $k . "}}"];
            } elseif (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return null;
            }
        }

        return $value;
    }

    private function resolvePlaceholder(array $matches): string
    {
        $key = trim($matches[1]);
        $filter = $matches[2] ?? false;

        $value = $this->getValueFromFastArray($key);

        if ($value === null) {
            return "{{" . $key . "}}";
        }

        if (is_array($value) || is_object($value)) {
            return is_array($value) ? "Array" : "Object";
        }

        if ($filter !== "html") {
            $value = htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
        }

        return (string)$value;
    }
}

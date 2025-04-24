<?php
namespace Core;

class TemplateEngineNew
{
    private $fast_array = [];

    public function __construct(array $fast_array = [])
    {
        $this->fast_array = $this->prepareExtraVars($fast_array);
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
                        : "");
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
                return $this->processForeach($matches);
            },
            $output
        );
    }

    private function processForeach(array $matches): string
    {
        $arrayKey = trim($matches[1]);
        $content = $matches[2];
        $output = "";

        $value = $this->getValueFromFastArray($arrayKey);

        if (!is_array($value)) {
            return "";
        }

        foreach ($value as $key => $item) {
            // Создаем новый контекст для вложенных элементов
            $context = [
                'key' => $key,
                'value' => $item
            ];

            // Обрабатываем вложенные элементы
            $loopContent = $this->processLoopContent($content, $context);
            $output .= $loopContent;
        }

        return $output;
    }

    private function processLoopContent(string $content, array $context): string
    {
        // Обрабатываем вложенные плейсхолдеры
        $content = $this->replaceLoopPlaceholders($content, $context);

        // Обрабатываем вложенные условия
        $content = $this->processIfConditions($content, $context);

        // Обрабатываем вложенные циклы
        $content = $this->replaceForeachLoop($content);

        return $content;
    }

    private function replaceLoopPlaceholders(string $content, array $context): string
    {
        return preg_replace_callback(
            "/{{\s*(key|value)(?:\.([a-zA-Z0-9_.-]+))?\s*}}/sm",
            function ($matches) use ($context) {
                $var = $matches[1];
                $propertyPath = $matches[2] ?? null;

                $value = $context[$var] ?? null;

                if ($propertyPath && is_array($value)) {
                    $keys = explode('.', $propertyPath);
                    foreach ($keys as $key) {
                        if (isset($value[$key])) {
                            $value = $value[$key];
                        } else {
                            return "";
                        }
                    }
                }

                return is_scalar($value) ? htmlspecialchars($value, ENT_QUOTES, "UTF-8") : "";
            },
            $content
        );
    }

    private function processIfConditions(string $content, array $context = []): string
    {
        return preg_replace_callback(
            "/\\\\?{%\s*if\s+([^ ]+)\s*(==|!=)\s*([^ ]+)\s*%}(.*?){%\s*endif\s*%}/sm",
            function ($matches) use ($context) {
                if (strpos($matches[0], '\\') === 0) {
                    return ltrim($matches[0], '\\');
                }

                $leftValue = $this->getComparisonValue($matches[1], $context);
                $operator = trim($matches[2]);
                $rightValue = $this->getComparisonValue($matches[3], $context);

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
        // Проверяем контекст (key/value в цикле)
        if (isset($context[$variable])) {
            return $context[$variable];
        }

        // Проверяем вложенные свойства (value.property)
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

        // Проверяем глобальные переменные
        return $this->getValueFromFastArray($variable);
    }

    private function getValueFromFastArray(string $key)
    {
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
            $value = htmlspecialchars($value, ENT_QUOTES, "UTF-8");
        }

        return $value;
    }
}

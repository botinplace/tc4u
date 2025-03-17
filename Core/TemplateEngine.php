<?php
namespace Core;

class TemplateEngine
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
                        : ""));
        }
        return $fast_array;
    }

    public function render(string $template, array $data = []): string
    {
        $this->fast_array = array_merge($this->fast_array, $this->prepareExtraVars($data));
        $output = $this->replaceForeachLoop($template, $this->fast_array);
        $output = $this->replacePlaceholders($output, $this->fast_array);
        $output = $this->processIfConditions($output, null, null, $this->fast_array);
        return $output;
    }

   

   private function replacePlaceholders(
    string $output,
    array $fast_array,
    bool $inforeach=false
): string {
    return preg_replace_callback(
        "/\\\\?{\\{?\s*([a-zA-Z0-9-_.]*)\s*[|]?\s*([a-zA-Z0-9]*)\s*\\}?\\}/sm",
        function ($matches) use ($fast_array,$inforeach) {
            // Если плейсхолдер экранирован (начинается с {{{)
            if ( (strpos($matches[0], '\\') === 0) ) {
                // Возвращаем плейсхолдер без экранирования
                return "{{" . $matches[1] . "}}";
            }

            if( $inforeach && ( $matches[1] == 'key' || $matches[1] == 'value') ){
                return "{{" . $matches[1] . $inforeach."}}";
            }

            // Обычный плейсхолдер
            return $this->resolvePlaceholder($matches, $fast_array);
        },
        $output
    );
}

private function replaceForeachLoop(string $output, array $fast_array): string
{
    return preg_replace_callback(
        "/\\\\?{%\s*foreach\s+([a-zA-Z0-9-_.]*)\s*%}((?:(?R)|.*?)*){%\s*endforeach\s*%}/sm",
        function ($matches) use ($fast_array) {
            if (strpos($matches[0], '\\') === 0) {
                return ltrim($matches[0], '\\');
            }
            return $this->processForeach($matches, $fast_array);
        },
        $output
    );
}

    private function getValueFromFastArray(string $key, array $fast_array)
{
    $keys = explode('.', $key);
    $value = $fast_array;

    foreach ($keys as $k) {
        if (isset($value["{{" . $k . "}}"])) {
            $value = $value["{{" . $k . "}}"];
        } elseif (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return null; // Если ключ не найден, возвращаем null
        }
    }

    return $value;
}
 
 private function resolvePlaceholder(array $matches, array $fast_array): string
{
    $filter = $matches[2] ?? false;
    $key = trim($matches[1]);

    // Получаем значение из fast_array
    $value = $this->getValueFromFastArray($key, $fast_array);

    // Если значение не найдено, возвращаем оригинальный плейсхолдер
    if ($value === null) {
        return "{{" . $key . "}}";
    }


    // Обрабатываем значение в зависимости от типа
    if (is_array($value)) {
        return "Array";
    }
    if (is_object($value)) {
        return "Object";
    }

    if($filter!='html' ){ $value=preg_replace('/\{%(\s*)if/', '\{%$1if', $value);}
    // Применяем фильтр
    return $filter === "html"
        ? html_entity_decode($value)
        : htmlspecialchars(html_entity_decode($value), ENT_QUOTES, "UTF-8");
}

private function processForeach(array $matches, array $fast_array): string
{
    $arrayKey = "{{" . trim($matches[1]) . "}}";
    $content = $matches[2];
    $output = "";

    $key = trim($matches[1]);
    $fast_array[$arrayKey] = $this->getValueFromFastArray($key, $fast_array);

    if (
        empty($fast_array[$arrayKey]) ||
        !is_array($fast_array[$arrayKey])
    ) {
        return ""; // Можно выбрасывать исключение или вести лог
    }

    foreach ($fast_array[$arrayKey] as $key => $value) {
        // Сохраняем текущие значения key и value из внешнего цикла
        $outerKey = $key;
        $outerValue = $value;

        // Обрабатываем вложенные плейсхолдеры
        $loopContent = $this->replaceLoopPlaceholders(
            $content,
            $value,
            $key
        );

        // Обработка условий внутри цикла
        $loopContent = $this->processIfConditions(
            $loopContent,
            $key,
            $value,
            $fast_array
        );

        // Обрабатываем вложенные циклы
        $loopContent = $this->replaceForeachLoop($loopContent, $fast_array);

        $output .= $loopContent;
    }

    return $output;
}

private function replaceLoopPlaceholders(
    string $content,
    $value,
    $key
): string {
    // Обработка вложенных плейсхолдеров, таких как {{ value.user.profile.name }}
    $content = preg_replace_callback(
        "/{{\s*value\.([a-zA-Z0-9_.-]+)\s*}}/sm",
        function ($innerMatches) use ($value) {
            if (is_array($value)) {
                $keys = explode('.', $innerMatches[1]);
                $currentValue = $value;
                foreach ($keys as $nestedKey) {
                    if (isset($currentValue[$nestedKey])) {
                        $currentValue = $currentValue[$nestedKey];
                    } else {
                        return ""; // Если ключ не найден, возвращаем пустую строку
                    }
                }
                return htmlspecialchars($currentValue, ENT_QUOTES, "UTF-8");
            }
            return "";
        },
        $content
    );

    // Обработка простого плейсхолдера {{ value }}
    $content = preg_replace_callback(
        "/{{\s*value\s*}}/sm",
        function ($innerMatches) use ($value) {
            if (is_array($value)) {
                return "Array";
            }
            return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
        },
        $content
    );

    // Обработка плейсхолдера {{ key }}
    $content = preg_replace_callback(
        "/{{\s*key\s*}}/sm",
        function ($innerMatches) use ($key) {
            return htmlspecialchars($key, ENT_QUOTES, "UTF-8");
        },
        $content
    );

    return $content;
}

private function processIfConditions(
    string $content,
    ?string $key = null,
    mixed $value = null,
    array $fast_array
): string {
    return preg_replace_callback(
        "/\\\\?{%\s*if\s+([^ ]+)\s*(==|!=)\s*([^ ]+)\s*%}(.*?){%\s*endif\s*%}/sm",
        function ($ifMatches) use ($key, $value, $fast_array) {
            if ( (strpos($ifMatches[0], '\\') === 0) ) {
                return ltrim($ifMatches[0],'\\');
            }
            $leftValue = $this->getValueForComparison(
                trim($ifMatches[1]),
                $key,
                $value,
                $fast_array
            );
            $operator = trim($ifMatches[2]);
            $rightValue = $this->getValueForComparison(
                trim($ifMatches[3]),
                $key,
                $value,
                $fast_array
            );

            if (
                ($operator === "==" && $leftValue == $rightValue) ||
                ($operator === "!=" && $leftValue != $rightValue)
            ) {
                return $ifMatches[4]; // Возвращаем содержимое, если условие истинно
            }
            return ""; // Возвращаем пустую строку, если условие ложно
        },
        $content
    );
}

private function getValueForComparison(
    $variable,
    ?string $key = null,
    mixed $value = null,
    array $fast_array
) {
    // Если переменная является "key" или "value" внутри цикла
    if ($variable === "key" && $key !== null) {
        return $key;
    }
    if ($variable === "value" && $value !== null) {
        return $value;
    }

    // Если переменная содержит вложенные ключи (например, value.id)
    if (strpos($variable, '.') !== false) {
        $keys = explode('.', $variable);

        // Начинаем с $value
        $currentValue = $value;

        // Проходим по каждому ключу
        foreach ($keys as $nestedKey) {
            if ($nestedKey === 'value') {
                continue;
            }

            if (is_array($currentValue) && isset($currentValue[$nestedKey])) {
                $currentValue = $currentValue[$nestedKey];
            } elseif (is_object($currentValue) && isset($currentValue->$nestedKey)) {
                $currentValue = $currentValue->$nestedKey;
            } else {
                return null; // Если ключ не найден, возвращаем null
            }
        }

        return $currentValue;
    }

    // Если переменная является ключом из fast_array
    if (isset($fast_array["{{" . $variable . "}}"])) {
        return $fast_array["{{" . $variable . "}}"];
    }

    // Если переменная является строкой (например, "true", "false", число и т.д.)
    return htmlspecialchars($variable); // Возвращаем значение как есть
}

}

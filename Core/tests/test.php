<?php
namespace Core;

class TemplateEngine
{
    private $fast_array = [];
    private $loopStack = []; // Стек для хранения контекста вложенных циклов

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
        $output = $this->processIfConditions($output, $this->fast_array);
        return $output;
    }

    private function replacePlaceholders(
        string $output,
        array $fast_array,
        bool $inLoop = false
    ): string {
        return preg_replace_callback(
            "/\\\\?{\\{?\s*([a-zA-Z0-9-_.]*)\s*[|]?\s*([a-zA-Z0-9]*)\s*\\}?\\}/sm",
            function ($matches) use ($fast_array, $inLoop) {
                if ((strpos($matches[0], '\\') === 0)) {
                    return "{{" . $matches[1] . "}}";
                }

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
                return null;
            }
        }

        return $value;
    }
 
private function resolvePlaceholder(array $matches, array $fast_array): string
{
    $filter = $matches[2] ?? false;
    $key = trim($matches[1]);

    // Проверяем доступ к родительским значениям через .parent
    if (strpos($key, 'parent.') === 0) {
        $levels = substr_count($key, 'parent.');
        $originalKey = substr($key, strrpos($key, '.') + 1);
        
        // Получаем значение из стека циклов
        if (count($this->loopStack) >= $levels) {
            $loopContext = $this->loopStack[count($this->loopStack) - $levels];
            
            if ($originalKey === 'key') {
                $value = $loopContext['key'];
            } elseif ($originalKey === 'value') {
                $value = $loopContext['value'];
            } else {
                // Обработка вложенных свойств родительского value
                $value = $this->getNestedValue($loopContext['value'], $originalKey);
            }
            
            return $this->applyFilter($value, $filter);
        }
        
        return "{{" . $key . "}}";
    }

    // Обычный плейсхолдер
    $value = $this->getValueFromFastArray($key, $fast_array);

    if ($value === null) {
        return "{{" . $key . "}}";
    }

    return $this->applyFilter($value, $filter);
}
    
private function getNestedValue($value, $path)
{
    if (empty($path)) {
        return $value;
    }

    $keys = explode('.', $path);
        foreach ($keys as $k) {
        if (is_array($value) && array_key_exists($k, $value)) {
            $value = $value[$k];
        } elseif (is_object($value) && property_exists($value, $k)) {
            $value = $value->$k;
        } else {
            return null;
        }
        
        // Преобразуем строковые булевые значения
        if (is_string($value)) {
            $lowerVal = strtolower($value);
            if ($lowerVal === 'true') return true;
            if ($lowerVal === 'false') return false;
        }
    }
    
    return $value;
}

    private function getValueForForeach(string $key, array $fast_array)
{
    // Если ключ содержит точку (например, value.permissions)
    if (strpos($key, '.') !== false) {
        $parts = explode('.', $key);
        $firstPart = array_shift($parts);
        
        // Проверяем текущий контекст цикла
        if (!empty($this->loopStack) && ($firstPart === 'key' || $firstPart === 'value')) {
            $currentLoop = end($this->loopStack);
            $value = $currentLoop[$firstPart];
            
            foreach ($parts as $part) {
                $value = $this->getNestedValue($value, $part);
                if ($value === null) break;
            }
            
            return $value;
        }
    }

    // Если это обычный ключ из fast_array
    return $this->getValueFromFastArray($key, $fast_array);
}


    private function applyFilter($value, $filter)
    {
        if (is_array($value)) {
            return "Array";
        }
        if (is_object($value)) {
            return "Object";
        }
        
        if ($filter !== "html") {
            $value = preg_replace('/\{%(\s*)if/', '\{%$1if', $value);
        }
        
        return $filter === "html"
            ? html_entity_decode($value)
            : htmlspecialchars(html_entity_decode($value), ENT_QUOTES, "UTF-8");
    }

private function processLoopContent(string $content, $value, $key, array $fast_array): string
{
    // Сначала обрабатываем вложенные циклы
    $processedContent = $this->replaceForeachLoop($content, $fast_array);
    
    // Затем заменяем плейсхолдеры
    $processedContent = $this->replaceLoopPlaceholders($processedContent, $value, $key);
    
    // Обрабатываем условия
    $processedContent = $this->processIfConditions($processedContent, $fast_array);
    
    return $processedContent;
}

private function processForeach(array $matches, array $fast_array): string
{
    $arrayKey = trim($matches[1]);
    $content = $matches[2];
    $output = "";

    // Получаем значение для цикла
    $loopValue = $this->getValueForForeach($arrayKey, $fast_array);

    if (empty($loopValue) || !is_array($loopValue)) {
        return "";
    }

    // Создаем новый контекст цикла
    $loopContext = [
        'key' => null,
        'value' => null,
        'parent' => !empty($this->loopStack) ? end($this->loopStack) : null
    ];
    
    // Добавляем контекст в стек
    array_push($this->loopStack, $loopContext);

    foreach ($loopValue as $key => $value) {
        // Обновляем ТОЛЬКО текущий контекст цикла (не затрагивая родительские)
        $currentContextIndex = count($this->loopStack) - 1;
        $this->loopStack[$currentContextIndex]['key'] = $key;
        $this->loopStack[$currentContextIndex]['value'] = $value;

        // Обрабатываем содержимое цикла
        $loopContent = $this->processLoopContent($content, $value, $key, $fast_array);
        $output .= $loopContent;
    }

    // Удаляем текущий контекст из стека
    array_pop($this->loopStack);

    return $output;
}

private function replaceLoopPlaceholders(
    string $content,
    $value,
    $key
): string {
    // Обрабатываем текущий контекст (разные варианты написания)
    $content = preg_replace_callback(
        '/{{\s*key\s*}}/i',
        function() use ($key) {
            return htmlspecialchars($key, ENT_QUOTES, "UTF-8");
        },
        $content
    );

    $content = preg_replace_callback(
        '/{{\s*value\s*}}/i',
        function() use ($value) {
            if (is_array($value)) {
                return "Array";
            }
            
            if (is_object($value)) {
                return "Object";
            }
            return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
        },
        $content
    );

    // Обрабатываем вложенные свойства value (с пробелами и без)
    $content = preg_replace_callback(
        '/{{\s*value\.([a-zA-Z0-9_.-]+)\s*}}/i',
        function ($matches) use ($value) {
            $nestedValue = $this->getNestedValue($value, $matches[1]);
            if (is_array($nestedValue)) {
                return "Array";
            }
            if (is_object($nestedValue)) {
                return "Object";
            }
            return $nestedValue !== null 
                ? htmlspecialchars($nestedValue, ENT_QUOTES, "UTF-8")
                : $matches[0];
        },
        $content
    );

    // Обрабатываем parent контекст, если он есть
    if (!empty($this->loopStack)) {
        $currentIndex = count($this->loopStack) - 1;
        
        // Обработка parent.key (с пробелами и без)
        if ($currentIndex > 0) {
            $parentContext = $this->loopStack[$currentIndex - 1];
            $content = preg_replace_callback(
                '/{{\s*parent\.key\s*}}/i',
                function() use ($parentContext) {
                    return htmlspecialchars($parentContext['key'], ENT_QUOTES, "UTF-8");
                },
                $content
            );
        }
        
        // Обработка parent.value.property (с пробелами и без)
        $content = preg_replace_callback(
            '/{{\s*parent\.value\.([a-zA-Z0-9_.-]+)\s*}}/i',
            function ($matches) use ($currentIndex) {
                if ($currentIndex > 0) {
                    $parentContext = $this->loopStack[$currentIndex - 1];
                    $nestedValue = $this->getNestedValue($parentContext['value'], $matches[1]);
                    if (is_array($nestedValue)) {
                        return "Array";
                    }
                    if (is_object($nestedValue)) {
                        return "Object";
                    }
                    return $nestedValue !== null 
                        ? htmlspecialchars($nestedValue, ENT_QUOTES, "UTF-8")
                        : '';
                }
                return '';
            },
            $content
        );
    }

    return $content;
}

private function processIfConditions(
        string $content,
        array $fast_array
    ): string {
        return preg_replace_callback(
             "/\\\\?{%\s*if\s+(!?\s*[a-zA-Z0-9-_.]+)\s*(?:([=!]=)\s*([^%]+))?\s*%}(.*?)(?:{%\s*else\s*%}(.*?))?{%\s*endif\s*%}/sm",
            function ($ifMatches) use ($fast_array) {
                if ((strpos($ifMatches[0], '\\') === 0)) {
                    return ltrim($ifMatches[0], '\\');
                }

                $negation = false;
                $variable = trim($ifMatches[1]);
                $elseContent = $ifMatches[5] ?? '';
                
                // Обработка отрицания (!variable)
                if (strpos($variable, '!') === 0) {
                    $negation = true;
                    $variable = trim(substr($variable, 1));
                }
                
                // Простая проверка существования/значения
                if (empty($ifMatches[2])) {
                    $value = $this->getValueForComparison($variable, $fast_array);
                    $conditionResult = $this->evaluateCondition($value, !$negation);
                    $outputContent = $conditionResult ? $ifMatches[4] : $elseContent;
                } else {
                    // Обработка сравнения
                    $operator = trim($ifMatches[2]);
                    $rightValue = $this->getValueForComparison(trim($ifMatches[3]), $fast_array);
                    $leftValue = $this->getValueForComparison($variable, $fast_array);
                    
                    $comparisonResult = ($operator === "==") 
                        ? ($leftValue == $rightValue) 
                        : ($leftValue != $rightValue);
                    
                    if ($negation) {
                        $comparisonResult = !$comparisonResult;
                    }
                    
                    $outputContent = $comparisonResult ? $ifMatches[4] : $elseContent;
                }

                // Рекурсивная обработка вложенных условий
                return $this->processIfConditions($outputContent, $fast_array);
            },
            $content
        );
    }
    
    private function evaluateComplexCondition(string $condition, array $fast_array): bool
{
    // Обработка отрицаний
    if (preg_match('/^!\s*(\w+)/', $condition, $matches)) {
        $value = $this->getValueForComparison($matches[1], $fast_array);
        return empty($value);
    }

    // Проверка существования значения
    $value = $this->getValueForComparison($condition, $fast_array);
    
    // Специальная обработка массивов
    if (is_array($value)) {
        return !empty($value);
    }
    
    return (bool)$value;
}
private function evaluateCondition($value, bool $expected): bool
{
    // Явная проверка булевых значений
    if (is_bool($value)) {
        return $expected ? $value : !$value;
    }

    // Оригинальная логика для других типов
    return $expected 
        ? !empty($value) || $value === "true" || $value === 1 || $value === "1"
        : empty($value) || $value === "false" || $value === 0 || $value === "0";
}

    private function getValueForComparison($variable, array $fast_array)
{
    // Удаляем лишние пробелы и кавычки
    $variable = trim($variable, " \t\n\r\0\x0B\"'");
    
    // Обработка строк в кавычках (например, "completed")
    if (preg_match('/^["\'](.+)["\']$/', $variable, $matches)) {
        return $matches[1];
    }

    // Проверка булевых значений
    if ($variable === 'true') return true;
    if ($variable === 'false') return false;
    
    // Проверка числовых значений
    if (is_numeric($variable)) {
        return $variable + 0; // Возвращаем как число
    }

    // Проверка доступа к родительским значениям через .parent
    if (strpos($variable, 'parent.') === 0) {
        $levels = substr_count($variable, 'parent.');
        $originalKey = substr($variable, strrpos($variable, '.') + 1);
        
        if (count($this->loopStack) >= $levels) {
            $loopContext = $this->loopStack[count($this->loopStack) - $levels];
            
            if ($originalKey === 'key') {
                return $loopContext['key'];
            } elseif ($originalKey === 'value') {
                return $loopContext['value'];
            } else {
                return $this->getNestedValue($loopContext['value'], $originalKey);
            }
        }
        return $variable;
    }

    // Проверка текущего контекста цикла
    if (!empty($this->loopStack)) {
        $currentLoop = end($this->loopStack);
        
        if ($variable === 'key') {
            return $currentLoop['key'];
        } elseif ($variable === 'value') {
            return $currentLoop['value'];
        }
    }

    // Обработка вложенных свойств (например, order.status)
    if (strpos($variable, '.') !== false) {
        $keys = explode('.', $variable);
        $firstKey = array_shift($keys);
        
        // Проверка в контексте цикла
        if (!empty($this->loopStack) && ($firstKey === 'key' || $firstKey === 'value')) {
            $currentLoop = end($this->loopStack);
            $value = $currentLoop[$firstKey];
            
            foreach ($keys as $k) {
                $value = $this->getNestedValue($value, $k);
                if ($value === null) break;
            }
            return $value;
        }
        
        
        // Проверка в fast_array
           $value = $this->getValueFromFastArray($variable, $fast_array);
    
        if (is_string($value)) {
            // Преобразуем строковые 'true'/'false' в boolean
            $lowerValue = strtolower($value);
            if ($lowerValue === 'true') return true;
            if ($lowerValue === 'false') return false;
        }
        
        return $value;
       }

  if (isset($fast_array["{{" . $variable . "}}"])) {
        return $fast_array["{{" . $variable . "}}"];
    }

    // Если переменная не найдена - возвращаем null
    return null;
}


}


$template ='<h1>Тестирование шаблонизатора</h1>

{{ test }}
{{ testarr.0 }}
<ul>
{% if 1 === 1 %}
1234
{% endif %}

{% foreach systems %}
{% if 1 == 1 %}
1234
{% endif %}
    <li>{{ key }} | {{ value.system_name }} | {% if value.selected %} 123 {% else %} 333 {% endif %}</li>
    <ul>
    {{ value.system_permissions }}
    {% if value.system_permissions %} 
    
    {% if !value.selected %}checked{% endif %}
    
    {% foreach value.system_permissions %}
    
    
        <li>{{ key }} {{ value.permission_name }} | {% if value.selected %} 123 {% else %} 333 {% endif %}</li>
    {% endforeach %}
    {% else %}
    7777777777777777
    {% endif %}
    </ul>
{% endforeach %}
</ul>

<h2>1. Базовые переменные</h2>
<p>Простая переменная: {{ title }}</p>
<p>Экранированная переменная: \{{ title }} (должна отобразиться как есть)</p>
<p>Фильтр html: {{ html_content|html }}</p>
<p>Фильтр по умолчанию: {{ default_content }}</p>

<h2>2. Условия</h2>
{% if show_section %}
    <p>Условие выполнено (show_section = true)</p>
{% endif %}

{% if non_existent_var %}
    <p>Это не должно отображаться</p>
{% endif %}

{% if user.role == "admin" %}
    <p>Пользователь является администратором</p>
{% endif %}

{% if user.role != "admin" %}
    <p>Пользователь не администратор</p>
{% endif %}

<h2>3. Простые циклы</h2>
<ul>
{% foreach items %}
    <li>{{ key }}: {{ value }}</li>
{% endforeach %}
</ul>

<h2>4. Вложенные циклы</h2>
<ul>
{% foreach categories %}
    <li>
        <strong>{{ value.name }}</strong> (ID: {{ key }})
        <ul>
        {% foreach value.products %}
            <li>
                Продукт #{{ key }}: {{ value.name }} - ${{ value.price }}
                {% if value.in_stock %}
                    (В наличии)
                {% endif %}
            </li>
        {% endforeach %}
        </ul>
    </li>
{% endforeach %}
</ul>

<h2>5. Доступ к родительскому контексту</h2>
<ul>
{% foreach departments %}
    <li>
        Отдел {{ value.name }}
        <ul>
        {% foreach value.employees %}
            <li>
                Сотрудник: {{ value.name }}<br>
                Должность: {{ value.position }}<br>
                Отдел: {{ parent.value.name }} (ID: {{ parent.key }})
            </li>
        {% endforeach %}
        </ul>
    </li>
{% endforeach %}
</ul>

<h2>6. Доступ к вложенным свойствам</h2>
<p>Имя пользователя: {{ user.name }}</p>
<p>Email пользователя: {{ user.contact.email }}</p>
<p>Город: {{ user.contact.address.city }}</p>

<h2>7. Комплексный пример</h2>
{% foreach orders %}
<div class="order">
    <h3>Заказ #{{ key }}</h3>
    {{ value.status }}
    <p>Статус: {% if value.status == "completed" %}Завершен{% else %}В обработке{% endif %}</p>
    <p>Клиент: {{ value.customer.name }}</p>
    
    <h4>Товары:</h4>
    <ul>
    {% foreach value.products %}
        <li>
            {{ value.name }} - {{ value.quantity }} × ${{ value.price }} = ${{ value.quantity * value.price }}
            {% if value.discount %}
                (Скидка {{ value.discount }}%)
            {% endif %}
        </li>
    {% endforeach %}
    </ul>
    
    <p><strong>Итого: ${{ value.total }}</strong></p>
</div>
{% endforeach %}';

$testData = [
  'test'=>'Hello world',
    'testarr'=>['test','arr'],
    'systems' => [
        [
            'system_name' => 'NormaCS',
            'selected'=>false,
            'system_permissions' => [
                ['permission_name' => 'Read'],
                ['permission_name' => 'Write']
            ]
        ],
        [
            'system_name' => 'Консультант-плюс',
            'selected'=>false,
            'system_permissions' => [
                ['permission_name' => 'View','selected'=>false],
                ['permission_name' => 'Edit','selected'=>false]
            ]
        ]
    ],
    'title' => 'Тестовый заголовок',
    'html_content' => '<b>Жирный текст</b>',
    'default_content' => '<i>Курсивный текст</i>',
    'show_section' => true,
    'user' => [
        'name' => 'Иван Петров',
        'role' => 'admin',
        'contact' => [
            'email' => 'ivan@example.com',
            'address' => [
                'city' => 'Москва'
            ]
        ]
    ],
    'items' => [
        'item1' => 'Первое значение',
        'item2' => 'Второе значение'
    ],
    'categories' => [
        1 => [
            'name' => 'Электроника',
            'products' => [
                ['name' => 'Смартфон', 'price' => 29999, 'in_stock' => true],
                ['name' => 'Ноутбук', 'price' => 59999, 'in_stock' => false]
            ]
        ],
        2 => [
            'name' => 'Одежда',
            'products' => [
                ['name' => 'Футболка', 'price' => 1999, 'in_stock' => true],
                ['name' => 'Джинсы', 'price' => 3999, 'in_stock' => true]
            ]
        ]
    ],
    'departments' => [
        'dev' => [
            'name' => 'Разработка',
            'employees' => [
                ['name' => 'Алексей', 'position' => 'Программист'],
                ['name' => 'Мария', 'position' => 'Тестировщик']
            ]
        ],
        'sales' => [
            'name' => 'Продажи',
            'employees' => [
                ['name' => 'Ольга', 'position' => 'Менеджер']
            ]
        ]
    ],
    'orders' => [
        1001 => [
            'status' => 'completed',
            'customer' => ['name' => 'Петр Иванов'],
            'products' => [
                ['name' => 'Мышь', 'price' => 999, 'quantity' => 1],
                ['name' => 'Клавиатура', 'price' => 1999, 'quantity' => 1, 'discount' => 10]
            ],
            'total' => 2798.1
        ]
    ]
];

$engine = new TemplateEngine();
echo $engine->render($template, $testData);

# Документация для шаблонизатора TemplateEngine

#### 1. Инициализация
```php
use Core\TemplateEngine;

// debugMode = true (разработка), false (продакшен)
$engine = new TemplateEngine($globalVars, $debugMode);
```

#### 2. Рендеринг шаблонов
```php
$html = $engine->render($templateContent, $data);
```
- `$templateContent`: строка с содержимым шаблона
- `$data`: массив с данными для шаблона

---

### Синтаксис шаблонов

#### 1. Вывод переменных
```twig
{{ variable }}
{{ user.name }}
{{ user.address.city }}
```

#### 2. Фильтры
```twig
{{ title | html }}      {# Безопасный вывод HTML #}
{{ 'style.css' | filetime }} {# Добавляет версию файла #}
```

#### 3. Условия
```twig
{% if user.isAdmin %}
  <p>Администратор</p>
{% else %}
  <p>Пользователь</p>
{% endif %}
```

Поддерживаемые операторы:
- `==`, `!=`, `>`, `<`, `>=`, `<=`
- `and`, `or`, `not`
- Группировка скобками

#### 4. Циклы
```twig
{% foreach items %}
  <p>{{ key }}: {{ value.name }}</p>
  
  {% foreach value.subitems %}
    <p>Subitem: {{ value }}</p>
  {% endforeach %}
{% endforeach %}
```

Доступные переменные в цикле:
- `key`: текущий ключ
- `value`: текущее значение
- `parent`: доступ к родительскому контексту

#### 5. Экранирование
```twig
\{{ this_will_not_be_parsed }}
\{% this_is_escaped %}
```

---

### Особенности и ограничения

1. **Безопасность данных**
   - Автоматическое экранирование HTML
   - Запрет объектов в продакшене
   ```php
   // В production
   $engine = new TemplateEngine([], false);
   ```

2. **Кеширование**
   - Шаблоны компилируются в PHP-код
   - Автоочистка кеша при превышении лимита (1000 файлов)
   - Ручная очистка:
   ```php
   $engine->clearCache(86400); // Удалить файлы старше 24 часов
   ```

3. **Контекст переменных**
   Приоритет доступа:
   1. Переменные цикла (`key`, `value`)
   2. Глобальные переменные
   3. Данные рендера

4. **Глубина обработки**
   - Макс. вложенность: 10 уровней
   - Защита от рекурсии

---

### Пример использования

```php
// Контроллер
$data = [
    'title' => '<b>Hello</b> World',
    'items' => [
        ['name' => 'Item 1'],
        ['name' => 'Item 2']
    ]
];

$template = <<<TPL
<html>
<head>
  <title>{{ title | html }}</title>
  <link href="{{ 'style.css' | filetime }}" rel="stylesheet">
</head>
<body>
  {% if items %}
    <ul>
    {% foreach items %}
      <li>{{ value.name }}</li>
    {% endforeach %}
    </ul>
  {% else %}
    <p>No items found</p>
  {% endif %}
</body>
</html>
TPL;

echo $engine->render($template, $data);
```

---

### Обработка ошибок

1. **Режим разработки (debugMode=true)**
   - Подробные ошибки в HTML-комментариях
   - Разрешение объектов
   - Автоматическая перекомпиляция шаблонов

2. **Продакшен (debugMode=false)**
   - Логирование ошибок в error_log
   - Вывод `<!-- Template rendering error -->`
   - Запрет передачи объектов

---

### Оптимизация для продакшена

1. Настройка веб-сервера:
```nginx
# Nginx
location ~ /cache/templates {
    deny all;
}
```

2. Права доступа:
```bash
chmod -R 775 /path/to/cache
chown -R www-data:www-data /path/to/cache
```

3. Крон для очистки кеша:
```bash
# Ежедневная очистка
0 3 * * * php /path/to/cleanup_script.php
```

4. PHP-настройки (php.ini):
```ini
opcache.enable=1
realpath_cache_size=4096K
```

---

### Ограничения

1. Нет поддержки:
   - Наследования шаблонов
   - Пользовательских функций
   - Включения подшаблонов

2. Производительность:
   - Оптимизирован для средних нагрузок
   - Для highload рекомендован Twig/Smarty
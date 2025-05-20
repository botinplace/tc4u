<?php
use Core\TemplateEngine;

$template ='<h1>Тестирование шаблонизатора</h1>

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

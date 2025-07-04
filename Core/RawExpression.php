<?php
namespace Core;

class RawExpression
{
    protected $value;

    public function __construct(string $value)
    {
        // Разрешаем только безопасные выражения
        if (!preg_match('/^(NOW\(\)|CURRENT_TIMESTAMP|UUID\(\)|[a-z_]+)$/i', $value)) {
            throw new \InvalidArgumentException('Invalid SQL expression');
        }
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

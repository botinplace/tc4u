<?php
namespace Core\Validator;

use InvalidArgumentException;

class Validator
{
    protected array $data;
    protected array $rules;
    protected array $errors = [];
    protected array $messages = [
        'required' => 'Поле :field обязательно для заполнения',
        'email' => 'Поле :field должно быть корректным email адресом',
        'min' => 'Поле :field должно быть не менее :min символов',
        'max' => 'Поле :field должно быть не более :max символов',
        'numeric' => 'Поле :field должно быть числом',
        'integer' => 'Поле :field должно быть целым числом',
        'string' => 'Поле :field должно быть строкой',
        'boolean' => 'Поле :field должно быть логическим значением',
        'confirmed' => 'Поле :field не совпадает с подтверждением',
        'unique' => 'Значение поля :field уже существует',
        'exists' => 'Выбранное значение для :field не существует',
    ];

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public function validate(): bool
    {
        foreach ($this->rules as $field => $rule) {
            $rules = is_array($rule) ? $rule : explode('|', $rule);
            
            foreach ($rules as $singleRule) {
                $this->applyRule($field, $singleRule);
            }
        }

        return empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }

    protected function applyRule(string $field, string $rule): void
    {
        $value = $this->data[$field] ?? null;

        // Обработка параметров правила (например, max:255)
        $ruleParts = explode(':', $rule, 2);
        $ruleName = $ruleParts[0];
        $ruleParams = $ruleParts[1] ?? null;

        // Подстановка значения в сообщение об ошибке
        $message = $this->messages[$ruleName] ?? 'Поле :field не прошло валидацию';
        $message = str_replace(':field', $field, $message);

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, $message);
                }
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, $message);
                }
                break;

            case 'min':
                $min = (int)$ruleParams;
                if (mb_strlen((string)$value) < $min) {
                    $message = str_replace(':min', $min, $message);
                    $this->addError($field, $message);
                }
                break;

            case 'max':
                $max = (int)$ruleParams;
                if (mb_strlen((string)$value) > $max) {
                    $message = str_replace(':max', $max, $message);
                    $this->addError($field, $message);
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, $message);
                }
                break;

            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, $message);
                }
                break;

            case 'string':
                if (!is_string($value)) {
                    $this->addError($field, $message);
                }
                break;

            case 'boolean':
                $acceptable = [true, false, 0, 1, '0', '1'];
                if (!in_array($value, $acceptable, true)) {
                    $this->addError($field, $message);
                }
                break;

            case 'confirmed':
                if ($value !== ($this->data[$field.'_confirmation'] ?? null)) {
                    $this->addError($field, $message);
                }
                break;
          
            default:
                throw new InvalidArgumentException("Неизвестное правило валидации: {$ruleName}");
        }
    }

    public function setCustomMessages(array $messages): void
    {
        $this->messages = array_merge($this->messages, $messages);
    }

    public function passes(): bool
    {
        return $this->validate();
    }

    public function fails(): bool
    {
        return !$this->validate();
    }
}

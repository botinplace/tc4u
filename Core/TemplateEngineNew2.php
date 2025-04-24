private $contextStack = [];

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
        // Создаем новый контекст с сохранением родительского
        $parentContext = $this->getCurrentContext();
        $context = [
            'key' => $key,
            'value' => $item,
            'parent' => $parentContext // Сохраняем родительский контекст
        ];
        
        $this->pushContext($context);
        
        // Обрабатываем вложенные элементы
        $loopContent = $this->processLoopContent($content);
        $output .= $loopContent;
        
        $this->popContext();
    }

    return $output;
}



private function replaceLoopPlaceholders(string $content): string
{
    return preg_replace_callback(
        "/{{\s*((?:parent\.)*)(key|value)(?:\.([a-zA-Z0-9_.-]+))?\s*}}/sm",
        function ($matches) {
            $parentLevels = substr_count($matches[1], 'parent.');
            $var = $matches[2];
            $propertyPath = $matches[3] ?? null;

            // Получаем текущий контекст
            $context = $this->getCurrentContext();
            
            // Поднимаемся по родительским контекстам
            for ($i = 0; $i < $parentLevels; $i++) {
                if (isset($context['parent'])) {
                    $context = $context['parent'];
                } else {
                    return ""; // Нет такого родительского уровня
                }
            }

            // Получаем значение
            $value = $context[$var] ?? null;

            // Обрабатываем вложенные свойства
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

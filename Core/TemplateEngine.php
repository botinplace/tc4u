<?php
namespace Core;

class TemplateEngine
{
    private $fast_array = [];

    public function __construct(array $fast_array = [])
    {
        $this->fast_array = $fast_array;
    }

    public function render(string $template, array $data = []): string
    {
        $this->fast_array = array_merge($this->fast_array, $this->prepareExtraVars($data));
        $output = $this->replaceForeachLoop($template, $this->fast_array);
        $output = $this->replacePlaceholders($output, $this->fast_array);
        $output = $this->processIfConditions($output, null, null, $this->fast_array);
        return $output;
    }

    private function replacePlaceholders(string $output, array $fast_array, bool $inforeach = false): string
    {
        return preg_replace_callback(
            "/\\\\?{\\{?\s*([a-zA-Z0-9-_.]*)\s*[|]?\s*([a-zA-Z0-9]*)\s*\\}?\\}/sm",
            function ($matches) use ($fast_array, $inforeach) {
                if (strpos($matches[0], '\\') === 0) {
                    return "{{" . $matches[1] . "}}";
                }

                if ($inforeach && ($matches[1] == 'key' || $matches[1] == 'value')) {
                    return "{{" . $matches[1] . $inforeach . "}}";
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

    private function processForeach(array $matches, array $fast_array): string
    {
        $arrayKey = "{{" . trim($matches[1]) . "}}";
        $content = $matches[2];
        $output = "";

        $key = trim($matches[1]);
        $fast_array[$arrayKey] = $this->getValueFromFastArray($key, $fast_array);

        if (empty($fast_array[$arrayKey]) || !is_array($fast_array[$arrayKey])) {
            return "";
        }

        foreach ($fast_array[$arrayKey] as $key => $value) {
            $loopContent = $this->replaceLoopPlaceholders($content, $value, $key);
            $loopContent = $this->processIfConditions($loopContent, $key, $value, $fast_array);
            $loopContent = $this->replaceForeachLoop($loopContent, $fast_array);
            $output .= $loopContent;
        }

        return $output;
    }

    private function replaceLoopPlaceholders(string $content, $value, $key): string
    {
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
                            return "";
                        }
                    }
                    return htmlspecialchars($currentValue, ENT_QUOTES, "UTF-8");
                }
                return "";
            },
            $content
        );

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

        $content = preg_replace_callback(
            "/{{\s*key\s*}}/sm",
            function ($innerMatches) use ($key) {
                return htmlspecialchars($key, ENT_QUOTES, "UTF-8");
            },
            $content
        );

        return $content;
    }

    private function processIfConditions(string $content, string $key = null, mixed $value = null, array $fast_array): string
    {
        return preg_replace_callback(
            "/\\\\?{%\s*if\s+([^ ]+)\s*(==|!=)\s*([^ ]+)\s*%}(.*?){%\s*endif\s*%}/sm",
            function ($ifMatches) use ($key, $value, $fast_array) {
                if (strpos($ifMatches[0], '\\') === 0) {
                    return ltrim($ifMatches[0], '\\');
                }
                $leftValue = $this->getValueForComparison(trim($ifMatches[1]), $key, $value, $fast_array);
                $operator = trim($ifMatches[2]);
                $rightValue = $this->getValueForComparison(trim($ifMatches[3]), $key, $value, $fast_array);

                if (($operator === "==" && $leftValue == $rightValue) || ($operator === "!=" && $leftValue != $rightValue)) {
                    return $ifMatches[4];
                }
                return "";
            },
            $content
        );
    }

    private function getValueForComparison($variable, string $key = null, mixed $value = null, array $fast_array)
    {
        if ($variable === "key" && $key !== null) {
            return $key;
        }
        if ($variable === "value" && $value !== null) {
            return $value;
        }

        if (strpos($variable, '.') !== false) {
            $keys = explode('.', $variable);
            $currentValue = $value;

            foreach ($keys as $nestedKey) {
                if ($nestedKey === 'value') {
                    continue;
                }

                if (is_array($currentValue) && isset($currentValue[$nestedKey])) {
                    $currentValue = $currentValue[$nestedKey];
                } elseif (is_object($currentValue) && isset($currentValue->$nestedKey)) {
                    $currentValue = $currentValue->$nestedKey;
                } else {
                    return null;
                }
            }

            return $currentValue;
        }

        if (isset($fast_array["{{" . $variable . "}}"])) {
            return $fast_array["{{" . $variable . "}}"];
        }

        return htmlspecialchars($variable);
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
        $fast_array["{{this_project_version}}"] = "v.1.0.0";
        $fast_array["{{SITE_URI}}"] = FIXED_URL;
        return $fast_array;
    }
}

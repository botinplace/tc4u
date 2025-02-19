<?php
namespace Core;

use Core\Response;
use Core\Request;

class Controller
{
    public $baseTemplate = "base";
    public $contentFile = "";
    public $content = "";
    public $params = [];
    public $xmlhttprequest = false;
    public $reload_page = false;
    public $page_not_found = false;
    public $get_main_block_only = false;
    public $pagedata;
    private $fast_array;
    private $response;

    public function __construct($pagedata)
    {
        $routename = isset($pagedata['routename'])?$pagedata['routename']:'';
        $this->pagedata = $this->loadPageData( $routename );
        $this->pagedata = array_merge($pagedata,$this->pagedata);
        $this->response = new Response();
        $this->xmlhttprequest = Request::isAjax();
        $this->baseTemplate = isset($this->pagedata["baseTemplate"]) ? trim($this->pagedata["baseTemplate"]) : "base";
        $this->contentFile = isset($this->pagedata["contentFile"]) ? trim($this->pagedata["contentFile"]) : "";
        $this->content = $this->loadContent($this->contentFile);
        $this->get_main_block_only =
            Request::header("X-Get-Main-Content-Only", false) ||
            (Request::get("GetMainContentOnly") &&
                !empty(Request::get("GetMainContentOnly")) &&
                (bool) Request::get("GetMainContentOnly"))
                ? true
                : false;
    }

    // тут лучше убрать в шаблон {{ includeCSS('filename' or ['filename1','filename2']) }}
    function includeCSS($files)
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        foreach ($files as $file) {
            if (file_exists(SITE . $file)) {
                echo '<link rel="stylesheet" href="' .
                    URI_FIXER .
                    BASE_URL .
                    $file .
                    "?v=" .
                    filemtime(SITE . $file) .
                    '" type="text/css">' .
                    PHP_EOL;
            }
        }
    }

    public function renderEmptyPage()
    {
        $this->content = $this->content ?: "СТРАНИЦА В РАЗРАБОТКЕ";
        $this->render();
        exit();
    }

    private function handleAjaxRequest($extra_vars)
    {
        if ($this->xmlhttprequest && !$this->get_main_block_only) {
		$data = $extra_vars["json_response"] ?? [];
		$this->response->setJsonBody( $data );
		$this->response->send();
        }
    }

private function loadPageData($pagename = ''): array
{
    $filePath = APP . "Config/pagedata.php";

    if (!file_exists($filePath)) {
		trigger_error("Ошибка: файл не найден - $filePath", E_USER_WARNING);
        return [];
    }

    try {
        $pagedata = include $filePath;

        if (!is_array($pagedata)) {
			trigger_error("Данные в файле $filePath должны быть массивом.");
            return [];
        }
        
        return $pagedata[$pagename] ?? [];
        
    } catch (\Throwable $e) {
		trigger_error("Ошибка при загрузке файла $filePath: " . $e->getMessage(), E_USER_WARNING);
        return [];
    }
}

    
    private function loadContent(string $contentFile): string
    {
        $filePath = APP . "Content/" . ucfirst($contentFile) . "Content.html";
        return file_exists($filePath) ? file_get_contents($filePath) : "";
    }

    private function replacePlaceholdersInOutput(
        $output,
        array $fast_array
    ): string {
        $output = $this->replacePlaceholders($output, $fast_array);
        $output = $this->replaceForeachLoop($output, $fast_array);
        return $output;
    }

    private function replacePlaceholders(
        string $output,
        array $fast_array
    ): string {
        return preg_replace_callback(
            "/{{\s*([a-zA-Z0-9-_.]*)\s*[|]?\s*([a-zA-Z0-9]*)\s*}}/sm",
            function ($matches) use ($fast_array) {
                return $this->resolvePlaceholder($matches, $fast_array);
            },
            $output
        );
    }

    private function replaceForeachLoop(
        string $output,
        array $fast_array
    ): string {
        return preg_replace_callback(
            "/{%\s*foreach\s+([a-zA-Z0-9-_.]*)\s*%}(.*?){%\s*endforeach\s*%}/sm",
            function ($matches) use ($fast_array) {
                return $this->processForeach($matches, $fast_array);
            },
            $output
        );
    }

    private function resolvePlaceholder(
        array $matches,
        array $fast_array
    ): string {
        $filter = $matches[2] ?? false;
        $key = "{{" . trim($matches[1]) . "}}";
        $value = $fast_array[$key] ?? $key;

        if (is_array($value)) {
            return "Array";
        }
        if (is_object($value)) {
            return "Object";
        }

        return $filter === "html"
            ? html_entity_decode($value)
            : htmlspecialchars(html_entity_decode($value), ENT_QUOTES, "UTF-8");
    }

    private function processForeach(array $matches, array $fast_array): string
    {
        $arrayKey = "{{" . trim($matches[1]) . "}}";
        $content = $matches[2];
        $output = "";

        if (
            empty($fast_array[$arrayKey]) ||
            !is_array($fast_array[$arrayKey])
        ) {
            return ""; // Можно выбрасывать исключение или вести лог
        }

        foreach ($fast_array[$arrayKey] as $key => $value) {
            $loopContent = $this->replaceLoopPlaceholders(
                $content,
                $value,
                $key
            );

            // Обработка условий
            $loopContent = $this->processIfConditions(
                $loopContent,
                $key,
                $value,
                $fast_array
            );
            $output .= $loopContent;
        }

        return $output;
    }

    private function replaceLoopPlaceholders(
        string $content,
        $value,
        $key
    ): string {
        $content = preg_replace_callback(
            "/{{\s*value\s*}}/sm",
            function ($innerMatches) use ($value) {
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

    private function processIfConditions(
        string $content,
        $key,
        $value,
        array $fast_array
    ): string {
        return preg_replace_callback(
            "/{%\s*if\s+([^ ]+)\s*(==|!=)\s*([^ ]+)\s*%}(.*?){%\s*endif\s*%}/sm",
            function ($ifMatches) use ($key, $value, $fast_array) {
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

    // Вспомогательная функция для получения значения по сравнению
    private function getValueForComparison($variable, $key, $value, $fast_array)
    {
        if ($variable == "key") {
            return $key; // Возвращаем ключ
        } elseif ($variable == "value") {
            return $value; // Возвращаем значение
        } else {
            return $fast_array["{{" . $variable . "}}"] ??
                htmlspecialchars($variable); // Проверяем в fast_array
        }
    }

    /*
private function replaceFor(array $matches): string {
    $start = (int)$matches[1];
    $end = (int)$matches[2];
    $step = (int)$matches[3];
    $content = $matches[4];
    $output = '';

    for ($i = $start; $i <= $end; $i += $step) {
        $loopContent = $content;
        $loopContent = preg_replace_callback(
            '/{{\s*i\s*}}/sm',
            function($innerMatches) use ($i) {
                return htmlspecialchars($i, ENT_QUOTES, 'UTF-8'); 
            },
            $loopContent
        );
        $output .= $loopContent;
    }
    return $output;
}
*/

    public function render(array $extra_vars = [])
    {
        ob_get_clean();

        if (!$this->page_not_found) {
            $this->setCacheHeaders();
        }

        $this->pagedata["content"] = $this->content;

        if (!file_exists(TEMPLATES . "/" . $this->baseTemplate . ".php")) {
            return $this->handleTemplateNotFound();
        }

        ob_start();
        $this->handleAjaxRequest([]);
        extract($this->pagedata);
        $this->fast_array = $this->prepareExtraVars($extra_vars);

        if ($this->get_main_block_only && $this->xmlhttprequest) {
            return $this->renderMainBlock($this->fast_array);
        } else {
            include TEMPLATES . "/" . $this->baseTemplate . ".php";
        }

        $this->response->setHtmlBody(
            $this->replacePlaceholdersInOutput(
                ob_get_clean(),
                $this->fast_array
            )
        );
        $this->response->send();
    }

    public function handleNotFound(): void
    {
	// заменить на $this->response->setHeader !!!
        header("Content-type: text/html; charset=utf-8");
        header("HTTP/1.0 404 Not Found");
	$this->content = $this->loadContent('404');
        $this->page_not_found = true;
        $this->render();
    }

    private function setCacheHeaders(): void
    {
	// заменить на $this->response->setHeader !! и проверить возможно это в конфиг вынести
        header("Cache-control: public");
        header(
            "Expires: " . gmdate("D, d M Y H:i:s", time() + 60 * 60) . " GMT"
        );
    }

    private function handleTemplateNotFound(): void
    {
        echo "No template";
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
        $fast_array["{{this_project_version}}"] = "v.1.0.0";
        $fast_array["{{SITE_URI}}"] = FIXED_URL;
        return $fast_array;
    }

    private function renderMainBlock(array $fast_array): void
    {
	$newheader = base64_encode($this->pagedata["pagetitle"] ?? "");
	$this->response->setHeader('X-Page-Title:',$newheader);
	    
        echo $this->replacePlaceholdersInOutput(
            $this->content,
            $this->fast_array
        );
        echo '<input type="hidden" id="NewTitleTextByOnlyMain" value="' .
            ($this->pagedata["pagetitle"] ?? "") .
            '" />';
        if ($this->reload_page) {
            echo '<input type="hidden" id="ReloadPageByOnlyMain" value="1" />';
        }
    }
}

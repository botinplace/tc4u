<?php
namespace Core;

use Core\Response;
use Core\Request;
use Core\TemplateEngine;

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
    private $response;
    private $templateEngine;

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
        
        $extra_vars["this_project_version"] = "v.1.0.0";
        $extra_vars["SITE_URI"] = FIXED_URL;

        $this->templateEngine = new TemplateEngine($extra_vars);
        
        if ($this->get_main_block_only && $this->xmlhttprequest) {
            return $this->renderMainBlock();
        } else {
            include TEMPLATES . "/" . $this->baseTemplate . ".php";
        }

        $this->response->setHtmlBody(
            $this->templateEngine->render( ob_get_clean() ) 
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


    private function renderMainBlock(): void
    {
	$newheader = base64_encode($this->pagedata["pagetitle"] ?? "");
	$this->response->setHeader('X-Page-Title:',$newheader);
	    
        echo $this->templateEngine->render( $this->content );

        echo '<input type="hidden" id="NewTitleTextByOnlyMain" value="' .
            ($this->pagedata["pagetitle"] ?? "") .
            '" />';
        if ($this->reload_page) {
            echo '<input type="hidden" id="ReloadPageByOnlyMain" value="1" />';
        }
    }
}

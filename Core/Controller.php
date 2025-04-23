<?php
namespace Core;

use Core\Config\Config;
use Core\Request;
use Core\Response;
use Core\Session;
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
    protected Response $response;
    private TemplateEngine $templateEngine;

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

public function render(array $extra_vars = []): void
{
    try {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    } catch (\Throwable $e) {
        error_log('Ошибка очистки буфера: ' . $e->getMessage());        
        // throw $e;
    }

    if (!$this->page_not_found) {
        $this->setCacheHeaders();
    }

    $this->pagedata["content"] = $this->content;

    if (!file_exists(TEMPLATES . $this->baseTemplate . ".php")) {
        $this->handleTemplateNotFound();
        return;
    }

    $this->handleAjaxRequest($extra_vars);

    $extra_vars["this_project_version"] = "v.1.0.0";
    $extra_vars["SITE_URI"] = Config::get('app.fixed_uri');
    $extra_vars["isUserAuthenticated"] = $this->isUserAuthenticated();
    $extra_vars["user"] = isset($extra_vars['auth']['user']) ? $extra_vars['auth']['user'] : (isset( $this->pagedata['auth']['user'] ) ? $this->pagedata['auth']['user'] : [] );
    $extra_vars['pagetitle'] = isset($extra_vars['pagetitle']) ? $extra_vars['pagetitle'] : (isset($this->pagedata['pagetitle']) ? $this->pagedata['pagetitle'] : '' );
    

    // Объединяем pagedata и extra_vars в один массив для шаблона
    $templateData = array_merge($this->pagedata, $extra_vars);
    //extract($this->pagedata);
    
    $this->templateEngine = new TemplateEngine($extra_vars);
    
    if ($this->get_main_block_only && $this->xmlhttprequest) {
        $this->renderMainBlock();
        return;
    }

    $content = $this->renderTemplate($this->baseTemplate, $templateData);

    $this->response
        ->setHtmlBody($this->templateEngine->render($content))
        ->send();
}

    protected function renderTemplate(string $template, array $data = []): string
    {
        $templatePath = TEMPLATES . $template . ".php";
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template file not found: {$templatePath}");
        }
    
        ob_start();
        try {
            extract($data, EXTR_SKIP);
            include $templatePath;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new \RuntimeException("Error rendering template {$template}: " . $e->getMessage(), 0, $e);
        }
        return ob_get_clean();
    }

    public function handleNotFound(): void
    {
        $this->response
            ->setStatusCode(404);
        $this->content = $this->loadContent('404');
        $this->page_not_found = true;
        $this->pagedata['pagetitle']='Ошибка';
        $this->render();
            //->setHtmlBody($this->loadContent('404'))
            //->send();
    }

    private function handleAjaxRequest($extra_vars): void
    {
        if ($this->xmlhttprequest && !$this->get_main_block_only) {
            $data = $extra_vars["json_response"] ?? [];
            $this->response
                ->setJsonBody($data)
                ->send();
        }
    }

    private function renderMainBlock(): void
    {
        $newheader = base64_encode($this->pagedata["pagetitle"] ?? "");
        $content = $this->templateEngine->render($this->content);
        
        $hiddenInputs = '<input type="hidden" id="NewTitleTextByOnlyMain" value="' 
            . htmlspecialchars($this->pagedata["pagetitle"] ?? "", ENT_QUOTES) . '" />';
        
        if ($this->reload_page) {
            $hiddenInputs .= '<input type="hidden" id="ReloadPageByOnlyMain" value="1" />';
        }

        $this->response
            ->setHeader('X-Page-Title', $newheader)
            ->setHtmlBody($content . $hiddenInputs)
            ->send();
    }

    private function setCacheHeaders(): void
    {
        $this->response
            ->setHeader('Cache-control', 'public')
            ->setHeader('Expires', gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
    }

    public function verifyCsrfToken(): void {
        if (!Session::validateCsrfToken(Request::get('_token'))) {
            $this->response->setStatusCode(403)->send();
        }
    }

    private function isUserAuthenticated(): bool
    {
        return Session::get('user') !== null;
    }

    public function renderEmptyPage(): void
    {
        $this->content = $this->content ?: "СТРАНИЦА В РАЗРАБОТКЕ";
        $this->render();
        exit();
    }

    private function loadPageData($pagename = ''): array
    {
        $filePath = CONFIG_DIR. "pagedata.php";

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

    private function handleTemplateNotFound(): void
    {
        $this->response
            ->setStatusCode(500)
            ->setTextBody("Template not found")
            ->send();
    }
}

<?php
namespace Core;

class Controller {
    public string $baseTemplate = 'base';
    public string $contentFile = '';
    public string $content = '';
    public array $params = [];
    public bool $xmlhttprequest = false;
    public bool $reload_page = false;
    public bool $page_not_found = false;
    public bool $get_main_block_only = false;

    public function __construct(string $template = 'base', string $contentFile = '') {
        $this->xmlhttprequest = $this->isAjaxRequest();
        $this->baseTemplate = $template;
        $this->contentFile = $contentFile;
        $this->content = $this->loadContent($this->contentFile);
    }

    private function isAjaxRequest(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'fetch');
    }

    private function loadContent(string $contentFile): string {
        $filePath = APP . 'Content/' . $contentFile . 'Content.php';
        return file_exists($filePath) ? file_get_contents($filePath) : '';
    }

    public function index() {
        var_dump(777);
    }

    public function render(array $vars = [], array $extra_vars = []): void {
        if ($this->page_not_found) {
            $this->handleNotFound();
        }

        $this->setCacheHeaders();
        if (!file_exists(TEMPLATES . '/' . $this->baseTemplate . '.php')) {
            return $this->handleTemplateNotFound();
        }

        ob_start();
        $this->handleAjaxRequest($extra_vars);

        extract($vars);
        $fast_array = $this->prepareExtraVars($extra_vars);

        if ($this->get_main_block_only && $this->xmlhttprequest) {
            $this->renderMainBlock($fast_array);
        } else {
            include TEMPLATES . '/' . $this->baseTemplate . '.php';
        }

        echo $this->replacePlaceholdersInOutput(ob_get_clean(), $fast_array);
    }

    private function replacePlaceholdersInOutput(string $output, array $fast_array): string {
        return preg_replace_callback(
            '/{{\s*([a-zA-Z0-9-_.]*)\s*[|]?\s*([a-zA-Z0-9]*)\s*}}/sm',
            fn($matches) => $this->replacePlaceholders($matches, $fast_array),
            $output
        );
    }

    private function handleNotFound(): void {
        header('Content-type: text/html; charset=utf-8');
        header("HTTP/1.0 404 Not Found");
    }

    private function setCacheHeaders(): void {
        header("Cache-control: public");
        header("Expires: " . gmdate("D, d M Y H:i:s", time() + 60 * 60 * 1) . " GMT");
    }

    private function handleTemplateNotFound(): void {
        echo 'No template';
    }

    private function prepareExtraVars(array $extra_vars): array {
        $fast_array = [];
        foreach ($extra_vars as $key => $value) {
            $fast_array['{{' . $key . '}}'] = is_scalar($value) 
                ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') 
                : (is_array($value) ? 'Array' : (is_object($value) ? 'Object' : ''));
        }
        $fast_array['{{this_project_version}}'] = 'v.1.0.0';
        return $fast_array;
    }

    private function renderMainBlock(array $fast_array): void {
        header("X-Page-Title: " . base64_encode($pagetitle));
        include TEMPLATES . '/onlymain.php';
        echo '<input type="hidden" id="NewTitleTextByOnlyMain" value="' . $pagetitle . '" />';
        echo '<input type="hidden" id="NewParentsUrlsByOnlyMain" value="' . htmlspecialchars($parentsurls, ENT_QUOTES, 'UTF-8') . '" />';
        if ($this->reload_page) {
            echo '<input type="hidden" id="ReloadPageByOnlyMain" value="1" />';
        }
    }

    private function replacePlaceholders(array $matches, array $fast_array): string {
        $filter = $matches[2] ?? false;
        $key = '{{' . trim($matches[1]) . '}}';
        $value = $fast_array[$key] ?? $key;

        return ($filter === 'html' ? html_entity_decode($value) : htmlspecialchars(html_entity_decode($value), ENT_QUOTES, 'UTF-8'));
    }
}
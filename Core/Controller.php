<?php
namespace Core;

class Controller {
    public $baseTemplate = 'base';
    public $contentFile = '';
    public $content = '';
    public $params = [];
    public $xmlhttprequest = false;
    public $reload_page = false;
    public $page_not_found = false;
    public $get_main_block_only = false;
    public $pagedata;
    private $fast_array;

    public function __construct($pagedata) {		
        $this->pagedata = $pagedata;
        $this->xmlhttprequest = Request::isAjax();
        $this->baseTemplate = $pagedata['basetemplate']?:'base';
        $this->contentFile = $pagedata['contentFile'];
        $this->content = $this->loadContent($this->contentFile);        
		$this->get_main_block_only = ( Request::get('GetMainContentOnly') && !empty( Request::get('GetMainContentOnly') ) && (bool)Request::get('GetMainContentOnly') ) ? true : false;
    }
    
	function includeCSS($files) {
		if (!is_array($files)) {
			$files = array($files);
		}

		foreach ($files as $file) {
			if (file_exists(SITE . $file)) {
				echo '<link rel="stylesheet" href="' . URI_FIXER . BASE_URL . $file . '?v=' . filemtime(SITE . $file) . '" type="text/css">' . PHP_EOL;
			}
		}
	}
    public function renderEmptyPage() {
        $this->content = $this->content ?: 'СТРАНИЦА В РАЗРАБОТКЕ';
        $this->render();
        exit();
    }
    
    private function handleAjaxRequest($extra_vars) {
        if ($this->xmlhttprequest && !$this->get_main_block_only) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($extra_vars['json_response'] ?? []);
            exit;
        }
    }
    
    private function loadContent(string $contentFile): string {
        $filePath = APP . 'Content/' . ucfirst($contentFile) . 'Content.html';
        return file_exists($filePath) ? file_get_contents($filePath) : '';
    }

    public final function render(array $extra_vars = []) {
        ob_get_clean();
		
        if (!$this->page_not_found) {
			$this->setCacheHeaders();
        }
		
		$this->pagedata['content'] = $this->content;
        
        if (!file_exists(TEMPLATES . '/' . $this->baseTemplate . '.php')) {
            return $this->handleTemplateNotFound();
        }

        ob_start();
        $this->handleAjaxRequest($extra_vars);
        extract($this->pagedata);
        $this->fast_array = $this->prepareExtraVars($extra_vars);

        if ($this->get_main_block_only && $this->xmlhttprequest) {
            return $this->renderMainBlock($this->fast_array);
        } else {			
            include TEMPLATES . '/' . $this->baseTemplate . '.php';
        }
        
        echo $this->replacePlaceholdersInOutput(ob_get_clean(), $this->fast_array);
    }

    private function replacePlaceholdersInOutput($output, array $fast_array): string {
        return preg_replace_callback(
            '/{{\s*([a-zA-Z0-9-_.]*)\s*[|]?\s*([a-zA-Z0-9]*)\s*}}/sm',
            function($matches) use ($fast_array) {				
                return $this->replacePlaceholders($matches, $fast_array);
            },
            $output
        );
    }

    public function handleNotFound(): void {
        header('Content-type: text/html; charset=utf-8');
        header("HTTP/1.0 404 Not Found");
		$this->page_not_found = true;
		$this->render();
    }

    private function setCacheHeaders(): void {
        header("Cache-control: public");
        header("Expires: " . gmdate("D, d M Y H:i:s", time() + 60 * 60) . " GMT");
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
		$fast_array['{{SITE_URI}}'] = FIXED_URL;
        return $fast_array;
    }

    private function renderMainBlock(array $fast_array): void {
        header("X-Page-Title: " . base64_encode($this->pagedata['pagetitle'] ?? ''));
        echo $this->content;
        echo '<input type="hidden" id="NewTitleTextByOnlyMain" value="' . ($this->pagedata['pagetitle'] ?? '') . '" />';
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
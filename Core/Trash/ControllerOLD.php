<?php
namespace Core;

class Controller {
	
	public $baseTemplate='base';
	public $contentFile='';
	public $content='';
	public $params=[];
	public $xmlhttprequest=false;
	public $reload_page=false;
	public $page_not_found=false;
	public $get_main_block_only=false;
	
	function __construct($template='base',$contentFile=''){
		
		$this->xmlhttprequest = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' || 
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'fetch'));
		
		$this->baseTemplate = $template;
		$this->contentFile = $contentFile;
		$this->content = isset($this->contentFile)
		? ( file_exists(APP.'Content/'.$this->contentFile.'Content.php') ? file_get_contents(APP.'Content/'.$this->contentFile.'Content.php') : '' ) 
		: ( file_exists(APP.'Content/'.$this->contentFile.'Content.php') ? file_get_contents(APP.'Content/'.$this->contentFile.'Content.php') : '' ) ;
	}
	
	
	public function index(){
		var_dump(777);
	}
	
	
	public function renderEmptyPage(){
		echo 'Страница в разработке!';
		return;
	}
	
    //public function render($template, $vars = [], $extra_vars = [], $page_not_found = false, $get_main_block_only = false, $reload_page = false) {
	public function render( $vars = [], $extra_vars = []) {
        if ($this->page_not_found) {
            $this->handleNotFound();
        }

        $this->setCacheHeaders();
		
        if (!file_exists(TEMPLATES . '/' . $this->baseTemplate . '.php')) {
            echo 'No template';
            return;
        }

        ob_start();

        if ($this->xmlhttprequest && !$this->get_main_block_only) {
            header('Content-Type: application/json; charset=utf-8');
            return json_encode($extra_vars['json_response'] ?? []);
        }

        extract($vars);

        // Обработка extra_vars
        $fast_array = $this->prepareExtraVars($extra_vars);

        if ($this->get_main_block_only && $this->xmlhttprequest) {
            $this->renderMainBlock($fast_array);
        } else {
			$content = $this->content;
            require TEMPLATES . '/' . $this->baseTemplate . '.php';
        }

        echo preg_replace_callback(
            '/{{\s*([a-zA-Z0-9-_.]*)\s*[|]?\s*([a-zA-Z0-9]*)\s*}}/sm',
            function ($matches) use ($fast_array) {
                return $this->replacePlaceholders($matches, $fast_array);
            },
            ob_get_clean()
        );
    }

    private function handleNotFound() {
        header('Content-type: text/html; charset=utf-8');
        header("HTTP/1.0 404 Not Found");
        header("HTTP/1.1 404 Not Found");
        header("Status: 404 Not Found");
    }

    private function setCacheHeaders() {
        header("Cache-control: public");
        header("Expires: " . gmdate("D, d M Y H:i:s", time() + 60 * 60 * 1) . " GMT");
    }

    private function prepareExtraVars($extra_vars) {
        $fast_array = [];
        foreach ($extra_vars as $key => $value) {
            $fast_array['{{' . $key . '}}'] = is_scalar($value) 
                ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') 
                : (is_array($value) ? 'Array' : (is_object($value) ? 'Object' : ''));
        }
        $fast_array['{{this_project_version}}'] = 'v.1.0.0';
        return $fast_array;
    }

    private function renderMainBlock($fast_array) {
        header("X-Page-Title: " . base64_encode($pagetitle));
        require TEMPLATES . '/onlymain.php';
        echo '<input type="hidden" id="NewTitleTextByOnlyMain" value="' . $pagetitle . '" />';
        echo '<input type="hidden" id="NewParentsUrlsByOnlyMain" value="' . htmlspecialchars($parentsurls, ENT_QUOTES, 'UTF-8') . '" />';
        if ($reload_page) {
            echo '<input type="hidden" id="ReloadPageByOnlyMain" value="1" />';
        }
    }

    private function replacePlaceholders($matches, $fast_array) {
        $filter = $matches[2] ?? false;
        $key = '{{' . trim($matches[1]) . '}}';
        $value = $fast_array[$key] ?? $key;

        return ($filter == 'html' ? html_entity_decode($value) : htmlspecialchars(html_entity_decode($value), ENT_QUOTES, 'UTF-8'));
    }
}
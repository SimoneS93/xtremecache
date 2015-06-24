<?php

class XtremeCache extends Module {
    
    public function __construct() {
        $this->name = 'xtremecache';
        $this->tab = 'frontend_features';
        $this->version = '1.0';
        $this->author = 'Simone Salerno';

        parent::__construct();

        $this->displayName = $this->l('Xtreme cache');
        $this->description = $this->l('Cache non-dynamic pages in the front office.');
        $this->bootstrap = true;
    }
    
    public function install() {
        return parent::install() && 
                $this->registerHook('actionDispatcher') &&
                $this->registerHook('actionRequestComplete');
    }
    
    /**
     * Check if page exists in cache
     * If it exists, serve and abort
     * @param array $params
     */
    public function hookActionDispatcher($params) {
        if (!$this->isActive()) return;
        
        $controller_type = $params['controller_type'];
        
        //if front page and user not logged
        if (Dispatcher::FC_FRONT === $controller_type) {
            $cache = $this->getCachedPage();
			
            if (FALSE !== $cache) {
                //empty output buffer
                ob_get_clean();
                die($cache);
            }
        }
    }
    
    /**
     * Cache page content for front pages
     * @param string $params
     */
    public function hookActionRequestComplete($params) {
        if (!$this->isActive()) return;
        
        $controller = $params['controller'];
        
        if (is_subclass_of($controller, 'FrontController')) {
            require_once $this->path(_PS_TOOL_DIR_.'minify_html/minify_html.class.php');
            
            $output = Minify_HTML::minify($params['output']);
            $filename = $this->getCacheFilename();
            $dirname = dirname($filename);
            
            //create cache dir if not exists
            if (!is_dir($dirname)) mkdir($dirname, 0666);
            
            file_put_contents($filename, $output);
			
        }
    }
    
    /**
     * Check if should use cache
     * @return boolean
     */
    private function isActive() {
		$active = true;
        $customer = Context::getContext()->customer;
        if ($customer && $customer instanceof Customer)
            $active = $active && !$customer->isLogged();
		
        return $active;
    }

    /**
     * Get cached content if exists
     */
    private function getCachedPage() {
        $filename = $this->getCacheFilename();
        
        //check age validity. Delete old cache files
        
        if (time() - filectime($filename) > 3*3600) {
            @unlink($filename);
            return FALSE;
        }
        
        return @file_get_contents($filename);
    }
    
    /**
     * Map url to local file
     * @param string $url
     * @return string
     */
    private function getCacheFilename() {
        return $this->path(sprintf(
                '%s/xcache/%s.html', 
                __DIR__, 
                md5(filter_input(INPUT_SERVER, 'REQUEST_URI'))
        ));
    }
    
    /**
     * Use correct DS for paths
     * @param string $path
     * @return string
     */
    private function path($path) {
        return str_replace('/', DS, $path);
    }
}

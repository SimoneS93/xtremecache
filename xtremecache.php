<?php

/**
 * Serve cached pages with no request processing
 * @author Salerno Simone
 * @version 1.0.3
 * @license MIT
 */
class XtremeCache extends Module {
    const CACHE_TTL = 18000; //Cache Time-To-Live
    
    public function __construct() {
        $this->name = 'xtremecache';
        $this->tab = 'frontend_features';
        $this->version = '1.0.3';
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
    
    public function uninstall() {
        //delete all cached files
        $dirname = dirname($this->getCacheFilename('fake_url'));
        array_walk(scandir($dirname), 'unlink');
        
        return $this->unregisterHook('actionDispatcher') &&
                $this->unregisterHook('actionRequestComplete') &&
                parent::uninstall();
    }
    
    /**
     * Check if page exists in cache
     * If it exists, serve and abort
     * @param array $params
     */
    public function hookActionDispatcher($params) {
        if (!$this->isActive())
            return;
        
        $controller_type = $params['controller_type'];
        
        //if front page and not in the checkout process
        if ($params['controller_class'] != 'OrderController' && 
                $params['controller_class'] != 'OrderOpcController' && 
                Dispatcher::FC_FRONT === $controller_type) {
            
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
        if (!$this->isActive())
            return;
        
        $controller = $params['controller'];
        
        if (is_subclass_of($controller, 'FrontController')) {
            require_once(_PS_TOOL_DIR_.'minify_html'.DS.'minify_html.class.php');
            
            $output = Minify_HTML::minify($params['output']);
            $filename = $this->getCacheFilename();
            $dirname = dirname($filename);
            
            //create cache dir if not exists
            if (!is_dir($dirname))
                mkdir($dirname, 0666);
            
            file_put_contents($filename, $output);
			
        }
    }
    
    /**
     * Check if should use cache
     * @return boolean
     */
    private function isActive() {
        $active = !Tools::getValue('ajax', false);
        $active = $active && filter_input(INPUT_SERVER, 'REQUEST_METHOD') === 'GET';
        
        //check that customer is not logged in
        $customer = $this->context->customer;
        if ($customer && $customer instanceof Customer)
            $active = $active && !$customer->isLogged();
        
        //for guest checkout, check that cart is empty
        $cart = $this->context->cart;
        if ($cart && $cart instanceof Cart){
            $products = $cart->getProducts();
            $active = $active && empty($products);
        }
		
        return $active;
    }

    /**
     * Get cached content if exists
     */
    private function getCachedPage() {
        $filename = $this->getCacheFilename();
        
        //check age validity. Delete old cache files
        if (file_exists($filename) && time() - filectime($filename) > static::CACHE_TTL) {
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
    private function getCacheFilename($url=null) {
        if ($url === null)
            $url = filter_input(INPUT_SERVER, 'REQUEST_URI');
        
        $url .= '-lang-'.$this->context->language->id.'-shop-'.$this->context->shop->id;
        $hash = md5($url);
        
        return __DIR__.DS.'xcache'.DS.$hash.'.html';
    }
}

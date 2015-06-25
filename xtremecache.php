<?php

/**
 * Serve cached pages with no request processing
 * @author Salerno Simone
 * @version 1.0.4
 * @license MIT
 */

require __DIR__.DS.'vendor'.DS.'phpfastcache.php';

class XtremeCache extends Module {
    /**
     * Cache Time-To-Live in seconds 
     */
    const CACHE_TTL = 30;
    /**
     * Cache engine
     * @var BasePhpFastCache
     */
    private $cache;
    
    public function __construct() {
        $this->name = 'xtremecache';
        $this->tab = 'frontend_features';
        $this->version = '1.0.4';
        $this->author = 'Simone Salerno';

        parent::__construct();

        $this->displayName = $this->l('Xtreme cache');
        $this->description = $this->l('Cache non-dynamic pages in the front office.');
        $this->bootstrap = true;
        $this->cache = $this->getCacheEngine();
    }
    
    /**
     * Install and register hooks
     * @return bool
     */
    public function install() {
        return parent::install() && 
                $this->registerHook('actionDispatcher') &&
                $this->registerHook('actionRequestComplete');
    }
    
    /**
     * Uninstall and clear cache
     * @return bool
     */
    public function uninstall() {
        //delete all cached files
        $this->cache->clean();
        
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
            
            $cached = $this->cache->get($this->getCacheKey());
            
            if (NULL !== $cached) {
               //empty output buffer
               ob_get_clean();
               die($cached);
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
            $this->cache->set($this->getCacheKey(), $output, static::CACHE_TTL);		
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
     * Get cache engine
     * @return BasePhpFastCache 
     */
    private function getCacheEngine() {
        phpFastCache::setup('path', __DIR__.DS.'xcache');
        return phpFastCache();
    }
    
    /**
     * Map url to cache key
     * @return string 
     */
    private function getCacheKey($url=NULL) {
        if ($url === NULL)
            $url = filter_input(INPUT_SERVER, 'REQUEST_URI');
        
        $url = 'lang-'.$this->context->language->id.'-shop-'.$this->context->shop->id.'-'.$url;
        $url = str_replace(_PS_BASE_URL_, '', $url);
        return $url;
    }
}

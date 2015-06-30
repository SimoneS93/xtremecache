<?php

/**
 * Serve cached pages with no request processing
 * @author Salerno Simone
 * @version 1.0.5
 * @license MIT
 */

require __DIR__.DS.'vendor'.DS.'phpfastcache.php';

class XtremeCache extends Module {
    /**
     * Cache Time-To-Live in seconds
     */
    const CACHE_TTL = 18000;
    
    /**
     * Cache engine
     * @var BasePhpFastCache
     */
    private $fast_cache;
    
    
    public function __construct() {
        $this->name = 'xtremecache';
        $this->tab = 'frontend_features';
        $this->version = '1.0.5';
        $this->author = 'Simone Salerno';

        parent::__construct();

        $this->displayName = $this->l('Xtreme cache');
        $this->description = $this->l('Cache non-dynamic pages in the front office.');
        $this->bootstrap = true;
        $this->fast_cache = $this->getFastCache();
    }
    
    /**
     * Handle hook non-explicitly handled
     * @param string $name hook name
     * @param array $arguments
     */
    public function __call($name, $arguments) {        
        if (0 === strpos(strtolower($name), 'hookaction')) {
            $this->fast_cache->clean();
        }
    }

    /**
     * Install and register hooks
     * @return bool
     */
    public function install() {        
        return parent::install() && 
                $this->registerHook('actionDispatcher') &&
                $this->registerHook('actionRequestComplete') &&
                $this->registerHook('actionCategoryAdd') &&
                $this->registerHook('actionCategoryUpdate') &&
                $this->registerHook('actionCategoryDelete') &&
                $this->registerHook('actionProductAdd') &&
                $this->registerHook('actionProductUpdate') &&
                $this->registerHook('actionProductDelete') &&
                $this->registerHook('actionProductSave') &&
                $this->registerHook('actionUpdateQUantity') &&
                $this->registerHook('actionEmptySmartyCache');
    }
    
    /**
     * Uninstall and clear cache
     * @return bool
     */
    public function uninstall() {
        //delete all cached files
        $this->fast_cache->clean();
        
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
        if ($params['controller_class'] !== 'OrderController' && 
            $params['controller_class'] !== 'OrderOpcController' && 
            Dispatcher::FC_FRONT === $controller_type) {
            
            $cached = $this->fast_cache->get($this->getCacheKey());
            
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
        
        if (is_subclass_of($controller, 'FrontController') &&
            !is_subclass_of($controller, 'OrderController') &&
            !is_subclass_of($controller, 'OrderOpcController')) {
            
            require_once(_PS_TOOL_DIR_.'minify_html'.DS.'minify_html.class.php');
            $key = $this->getCacheKey();
            $output = Minify_HTML::minify($params['output']);
            //mark page as cached
            $output = '<!-- served from cache with key '.$key.' -->'.$output;
            $this->fast_cache->set($key, $output, static::CACHE_TTL);		
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
    private function getFastCache() {
        phpFastCache::setup('path', __DIR__.DS.'xcache');
        return phpFastCache('sqlite');
    }
    
    /**
     * Map url to cache key
     * @return string 
     */
    private function getCacheKey($url=NULL) {
        if ($url === NULL)
            $url = filter_input(INPUT_SERVER, 'REQUEST_URI');
        
        $url = 'device-'.$this->context->getDevice().
                '-lang-'.$this->context->language->id.
                '-shop-'.$this->context->shop->id.'-'.
                $url;
        
        $url = md5($url);
        return $url;
    }
}

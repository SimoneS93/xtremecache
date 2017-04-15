<?php

/**
 * Full page cache for Prestashop
 *
 * @author Simone Salerno
 */
class Xtremecache extends Module
{
    /** @var int cache expire time in seconds **/
    const TTL = 3600*24*7;
    
    /** @var boolean wether cache should be cleaned on catalog updates **/
    const REACTIVE = true;
    
    /** @var boolean wether a custom header should be sent **/
    const CUSTOMER_HEADER = true;
    
    /** @var array of int Don"t cache certain languages **/
    const EXCLUDED_LANGS = [];
    
    /** @var array of int Don"t cache certain countries **/
    const EXCLUDE_COUNTRIES = [];
    
    /** @var array of int Don"t cache certain currencies **/
    const EXCLUDE_CURRENCIES = [];
    
    /** @var array of int Don"t cache certain shops **/
    const EXCLUDE_SHOPS = [];
    
    /** @var CacheCore **/
    private $cache;
    

    public function __construct()
    {
            $this->name = "xtremecache";
            $this->tab = "front_office_features";
            $this->version = "1.0.0";
            $this->author = "Simone Salerno";
            $this->need_instance = 0;
            $this->bootstrap = true;
            
            parent::__construct();

            $this->displayName = $this->l("Xtreme cache");
            $this->description = $this->l("Full page cache for Prestashop.");
            $this->ps_versions_compliancy = array("min" => "1.6", "max" => "1.6.99.99");
            
            $this->cache = Cache::getInstance();
    }
    
    /**
     * 
     * @return boolean
     */
    public function install()
    {
        $this->createHooks();
        
        return parent::install() &&
                $this->registerHook("actionCategoryAdd") &&
                $this->registerHook("actionCategoryUpdate") &&
                $this->registerHook("actionCategoryDelete") &&
                $this->registerHook("actionProductAdd") &&
                $this->registerHook("actionProductUpdate") &&
                $this->registerHook("actionProductDelete") &&
                $this->registerHook("actionProductSave") &&
                $this->registerHook("displayHeader") &&
                $this->registerHook("actionResponse");
    }
    
    /**
     * 
     * @return boolean
     */
    public function uninstall()
    {
        return parent::uninstall() &&
                $this->unregisterHook("actionCategoryAdd") &&
                $this->unregisterHook("actionCategoryUpdate") &&
                $this->unregisterHook("actionCategoryDelete") &&
                $this->unregisterHook("actionProductAdd") &&
                $this->unregisterHook("actionProductUpdate") &&
                $this->unregisterHook("actionProductDelete") &&
                $this->unregisterHook("actionProductSave") &&
                $this->unregisterHook("displayHeader") &&
                $this->unregisterHook("actionResponse");
    }

    /**
     * If a cached page exists for the current request
     * return it and abort 
     */
    public function hookDisplayHeader()
    {
        if ($this->isActive() && ($html = $this->load())) {
            ob_clean();
            
            if (static::CUSTOMER_HEADER) {
                header("X-Xtremecached: True");
            }
            
            die($html);
        }
    }
    
    /**
     * Store response in cache
     * 
     * @param array $params
     */
    public function hookActionResponse(array $params)
    {
        if ($this->isActive()) {
            $this->store($params["html"]);
        }
    }
    
    /**
     * Handle reactive hooks
     * 
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (static::REACTIVE && (0 === strpos(strtolower($name), 'hookaction'))) {
            $this->cache->flush();
        }
        else {
            return parent::__call($name, $arguments);
        }
    }

    /**
     * Test if current page is to be cached
     * 
     * @return boolean
     */
    private function isActive()
    {
        $cart = $this->context->cart;
        
        return     !_PS_DEBUG_PROFILING_                                            // skip when profiling
                && !_PS_MODE_DEV_                                                   // skip when debugging
                && is_a($this->context->controller, FrontControllerCore::class)     // skip on back-end
                && Configuration::get("PS_SHOP_ENABLE")                             // skip on catalogue mode
                && !Tools::getValue("ajax")                                         // skip on AJAX requests
                && filter_input(INPUT_SERVER, "REQUEST_METHOD") === "GET"           // skip on POST requests
                && $cart->id_customer < 1                                           // skip if user is logged in
                && $cart->nbProducts() < 1                                          // skip if cart is not empty
                && $this->isNotExcluded($cart->id_lang, static::EXCLUDED_LANGS)
                && $this->isNotExcluded($cart->id_shop, static::EXCLUDE_SHOPS)
                && $this->isNotExcluded($cart->id_currency, static::EXCLUDE_CURRENCIES)
                && $this->isNotExcluded($this->context->country->id, static::EXCLUDE_COUNTRIES);
    }
    
    /**
     * Get cached response
     * 
     * @return string
     */
    private function load()
    {
        return $this->cache->get($this->key());
    }
    
    /**
     * Store response
     * 
     * @param string $html
     * @return mixed
     */
    private function store($html)
    {
        $key = $this->key();
        $reponse = sprintf("<!-- cached on %s -->\n%s", date("Y-m-d H:i:s"), $html);
        
        return $this->cache->set($key, $reponse, static::TTL);
    }
    
    /**
     * Get unique key for current request
     * 
     * @return string
     */
    private function key()
    {
        return implode("-", [
            filter_input(INPUT_SERVER, "REQUEST_URI"),
            (int) $this->context->cart->id_currency,
            (int) $this->context->cart->id_lang,
            (int) $this->context->cart->id_shop,
            (int) $this->context->country->id,
            (int) $this->context->getDevice()
        ]);
    }
    
    /**
     * Check if given id is excluded from cache
     * 
     * @param int $id
     * @param array $pool
     * @return bool
     */
    private function isNotExcluded($id, array $pool)
    {
        return array_search($id, $pool) === false;
    }
    
    /**
     * Create needed hooks in DB
     * 
     */
    private function createHooks()
    {
        $id = Hook::getIdByName("actionResponse");
        
        if (!$id) {
            $hook = new Hook();
            $hook->hydrate([
                "name"          => "actionResponse",
                "title"         => "actionResponse",
                "description"   => "Run before sending response to the browser",
                "position"      => 1,
                "live_edit"     => 0
            ]);
            $hook->save();
        }
    }
}

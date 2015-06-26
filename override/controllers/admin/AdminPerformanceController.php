<?php

/**
 * Exec hook "clear smarty cache"
 * @author Simone Salerno
 * @version 1.0.0
 * @license MIT 
 */
class AdminPerformanceController extends AdminPerformanceControllerCore {
    
    /**
     * Exec hook "empty smarty cache"
     * @return mixed
     */
    public function postProcess() {
        if (Tools::getValue('empty_smarty_cache'))
            Hook::exec('actionEmptySmartyCache');
        
        return parent::postProcess();
    }
}

<?php

abstract class Controller extends ControllerCore {
    
    protected function smartyOutputContent($content)
	{
		$this->context->cookie->write();
		if (is_array($content))
			foreach ($content as $tpl)
				$html = $this->context->smarty->fetch($tpl);
		else
			$html = $this->context->smarty->fetch($content);

		$html = trim($html);

		if ($this->controller_type == 'front' && !empty($html))
		{
 			$dom_available = extension_loaded('dom') ? true : false;
 			if ($dom_available)
				$html = Media::deferInlineScripts($html);
			$html = trim(str_replace(array('</body>', '</html>'), '', $html))."\n";
			$this->context->smarty->assign(array(
				'js_def' => Media::getJsDef(),
				'js_files' => array_unique($this->js_files),
				'js_inline' => $dom_available ? Media::getInlineScript() : array()
			));
			$javascript = $this->context->smarty->fetch(_PS_ALL_THEMES_DIR_.'javascript.tpl');
                        
                        //SIMONE
                        Hook::exec('actionRequestComplete', array(
                            'controller' => $this,
                            'output' => $html.$javascript."\t</body>\n</html>"
                        ));
			echo $html.$javascript."\t</body>\n</html>";
		}
		else {
                        Hook::exec('actionRequestComplete', array(
                            'controller' => $this,
                            'output' => $html
                        ));
			echo $html;
                }
	}
}

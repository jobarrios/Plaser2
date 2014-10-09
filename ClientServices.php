<?php

class PLASER_ClientServices
{
	private $ns = 'http://configuration.plaser/schemas';

	function _init($fileconf)
	{
		$cachedir = null;
		if($GLOBALS['plaser_conf']->get('Cache_Enable') && $GLOBALS['plaser_conf']->get('Cache_Dir'))
		{
			$cachedir = $GLOBALS['plaser_conf']->get('Cache_Dir');
			if(substr($cachedir, -1) != '/') $cachedir.= '/';
		}
		if($cachedir)
		{
			$cachefile = $cachedir . 'plaser_' . md5($fileconf) . '.cxml';
			if(file_exists($cachefile)) return unserialize(file_get_contents($cachefile));
			else
			{
				$arr = $this->compilexml($fileconf);
				file_put_contents($cachefile, serialize($arr));
				return $arr;
			}
		}
		else return $this->compilexml($fileconf);
	}

	private function compilexml($fileconf)
	{
		if(!file_exists($fileconf)) throw new plaf('The configuration file: services.xml is missing.', 1601);
		if(!($xml = simplexml_load_file($fileconf))) throw new plaf("Invalid configuration file services.xml", 1602);
		$xml->registerXPathNamespace('x', $this->ns);
		$tmpserver = $GLOBALS['plaser_conf']->get('CorexServerDefault');
		$arrtmp = array();
		$Services = $xml->xpath("/x:plaser/x:service");
		foreach($Services as $service)
		{
			$id = (string) $service['identifier'];
			$arrtmp[$id] = new stdClass;
			$arrtmp[$id]->noplaser = (string) $service['noplaser'];
			$wsdl = trim((string) $service->wsdl);
			if(!parse_url($wsdl, PHP_URL_SCHEME))
			{
				if(!$tmpserver) throw new plaf("Configuration: Set the CorexServerDefault or set a host in the service: $id", 1603);
				$wsdl = $tmpserver.$wsdl;
			}
			$arrtmp[$id]->wsdl = $wsdl;
		}
		unset($xml);
		return $arrtmp;
	}
}//END class PLASER_ClientServices
?>

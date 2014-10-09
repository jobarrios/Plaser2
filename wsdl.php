<?php
/*****
WSDL class
*****/

class WSDL
{
	//Private
	private $service;
	private $wsdlurl;
	private $nswsdl = 'http://schemas.xmlsoap.org/wsdl/';
	private $nssoap = 'http://schemas.xmlsoap.org/wsdl/soap12/';

	function __construct($wsdlurl)
	{
		$this->wsdlurl = $wsdlurl;
		$urlnoext = substr($wsdlurl, 0, -5);
		$cachedir = null;

		if($GLOBALS['plaser_conf']->get('Cache_Enable') && $GLOBALS['plaser_conf']->get('Cache_Dir'))
		{
			$cachedir = $GLOBALS['plaser_conf']->get('Cache_Dir');
			if(substr($cachedir, -1) != '/') $cachedir.= '/';
		}
		if($cachedir)
		{
			$file = $cachedir . 'plaser_' . md5($urlnoext) . '.cwsdl';
			if(file_exists($file)) $this->service = unserialize(file_get_contents($file));
			else
			{
				$this->compilewsdl();
				file_put_contents($file, serialize($this->service));
			}
		}
		else $this->compilewsdl();
	}

	public function get_service()
	{
		return $this->service;
	}

	public function isset_header($method, $header)
	{
		//LB TODO: Should be a function for isset_method()
		if(!isset($this->service->methods[$method]))
		{
			if($GLOBALS['plaser_conf']->get('Development_Server')) $str = "WSDL: The method ({$method}) does not exist in the service: {$this->wsdlurl}";
			else $str = 'Invalid method.';
			throw new plaf($str, 1801);
		}
		return in_array($header, $this->service->methods[$method]);
	}

	private function compilewsdl()
	{
		if(!($wsdl = simplexml_load_file($this->wsdlurl))) throw new plaf("WSDL, reading file wsdl: {$this->wsdlurl}", 1802);
		$wsdl->registerXPathNamespace('w', $this->nswsdl);
		$this->service = new stdClass;
		$tmp = $wsdl->xpath("/w:definitions/@name");
		$this->service->identifier = (string) $tmp[0];
		$arrtmp = array();
		$Operations = $wsdl->xpath("/w:definitions/w:binding/w:operation");
		foreach($Operations as $operation)
		{
			$tmp = (string) $operation['name'];
			$arrtmp[$tmp] = array();
			$operation->registerXPathNamespace('w', $this->nswsdl);
			$operation->registerXPathNamespace('s', $this->nssoap);
			$Headers = $operation->xpath("./w:input/s:header");
			foreach($Headers as $header) $arrtmp[$tmp][] = (string) $header['part'];
		}
		$this->service->methods = $arrtmp;
	}

}//END class WSDL
?>

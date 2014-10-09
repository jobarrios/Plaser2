<?php
/*****
XSD class
*****/

class XSD
{
	//Public
	public $last_error = null;

	//Private
	private $xsd;

	function __construct($wsdlurl)
	{
		$urlnoext = substr($wsdlurl, 0, -5);
		$xsdurl = $urlnoext . '.xsd';
		$cachedir = null;

		if($GLOBALS['plaser_conf']->get('Cache_Enable') && $GLOBALS['plaser_conf']->get('Cache_Dir'))
		{
			$cachedir = $GLOBALS['plaser_conf']->get('Cache_Dir');
			if(substr($cachedir, -1) != '/') $cachedir.= '/';
		}
		if($cachedir)
		{
			$file = $cachedir . 'plaser_' . md5($urlnoext) . '.xsd';
			if(file_exists($file)) $this->xsd = file_get_contents($file);
			else
			{
				$this->xsd = file_get_contents($xsdurl);
				if(!file_exists($file)) file_put_contents($file, $this->xsd);
			}
		}
		else $this->xsd = file_get_contents($xsdurl);
		if(!$this->xsd) throw new plaf("XSD: reading xsd: $xsdurl", 1900);
	}

	public function validatesoap($soap)
	{
		$this->last_error = null;
		if(!$soap)
		{
			$this->last_error = 'Empty SOAP.';
			return false;
		}
		$Doc = new DOMDocument();
		$Doc->loadXML($soap);
		if(!$Node_ptr = $Doc->getElementsByTagName('Body')->item(0)) throw new plaf('XSD_Validate: missing Body in SOAP.', 1902);
		if(!$Node_ptr = &$Node_ptr->firstChild) throw new plaf('XSD_Validate: missing method in SOAP Body.', 1903);
		$Doc_payload = new DOMDocument();
		$Doc_payload->appendChild($Doc_payload->importNode($Node_ptr, true));

		ini_set('track_errors', '1');
		if(@$Doc_payload->schemaValidateSource($this->xsd))
		{
			ini_set('track_errors', '0');
			return true;
		}
		else
		{
			$this->last_error = $php_errormsg;
			ini_set('track_errors', '0');
			return false;
		}
	}

	public function get_xsd()
	{
		return $this->xsd;
	}

}//END class XSD
?>

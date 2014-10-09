<?php
/*****
PLASER_Corex_init: Initial process of the CoreX
*****/

function debug($var = null, $append = null)
{
	include_once 'Util/debug_dump.php';
	debug_dump($var, $append);
}

if(strlen($_SERVER['PHP_SELF']) > 100) exit('Error 2100');
//BEGIN General Configuration
require 'PLASER_CONF/plaser_conf.php';
$plaser_conf = new PLASER_Conf_class(1);
//END General Configuration

class PLASER_Corex_init
{
	//Private
	private $wsdlurl;
	private $service;
	private $services2serve = array();
	private $xsdconfigfile = 'PLASER/schemas/conf.xsd';
	private $ns = 'http://configuration.plaser/schemas'; //configuration namespace

	function __construct()
	{
		//BEGIN Set up Configuration file
		$configfile = $GLOBALS['plaser_conf']->get('Services');
		$dom = new DOMDocument();
		if($GLOBALS['plaser_conf']->get('Development_Server'))
		{
			if(!file_exists($configfile))
				throw new plaf("Missing configuration file: $configfile", 2101, 'Configuration');
			ini_set('track_errors', '1');
			if(!$dom->load($configfile))
				throw new plaf("Failed to load configuration file: $configfile: $php_errormsg", 2102, 'Configuration');
			if(!($xsdconfig = @file_get_contents($this->xsdconfigfile, true)))
				throw new plaf("Failed to read configuration schema: $configfile", 2103, 'Configuration');
			if(!@$dom->schemaValidateSource($xsdconfig))
				throw new plaf("Failed in Schema Validate: $configfile: $php_errormsg", 2104, 'Configuration');
			ini_set('track_errors', '0');
			unset($xsdconfig);
		}
		elseif(!@$dom->load($configfile)) throw new plaf("Configuration Fatal Error.", 2105, 'Configuration');
		//TODO Use only the dom xpath and not the simplexml one
		$xpaServices = new DOMXPath($dom);
		$xpaServices->registerNamespace('x', $this->ns);
		$xml = simplexml_import_dom($dom);
		$xml->registerXPathNamespace('x', $this->ns);
		//END Set up Configuration file

		//BEGIN Validating input
		if(pathinfo($_SERVER['PHP_SELF'], PATHINFO_EXTENSION)) throw new plaf('Invalid service request.', 2106);
		$servicereq = pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME);
		//Avoid XPath injection attacks
		$found = false;
		foreach($xml->xpath("//x:service[x:corex and not(@noplaser)]/x:wsdl") as $tmp)
		{
			$wsdl = (string) $tmp;
			$service = pathinfo($wsdl, PATHINFO_FILENAME);
			if(!strcmp($service, $servicereq)) { $found = true; break; }
		}
		if(!$found) throw new plaf('Service not found.', 2107);
		//END Validating input

		$this->wsdlurl = $GLOBALS['plaser_conf']->get('CorexServerDefault').$wsdl;
		$this->service = new stdClass;
		$tmp = $xml->xpath("//x:service[x:wsdl = '$wsdl']/@identifier");
		$this->service->identifier = (string) $tmp[0];
		if(strcmp(strtoupper($this->service->identifier), strtoupper($service)))
			throw new plaf('The service identifier and the name of the resource must to be similar.', 2108);

		if(!isset($GLOBALS['HTTP_RAW_POST_DATA']) || $GLOBALS['HTTP_RAW_POST_DATA'] == '')
			throw new plaf('Failed in SOAP Request, empty POST.', 2121, null, 'Client');

		$tmp = $xml->xpath("//x:service[x:wsdl = '$wsdl']/x:corex/@storageID");
		$this->service->storageID = count($tmp)?((string) $tmp[0]):null;

		$arrtmp = array();
		$Methods = $xml->xpath("//x:service[x:wsdl = '$wsdl']/x:corex/x:method");
		foreach($Methods as $method)
		{
			$tmp = (string) $method['name'];
			$arrtmp[$tmp] = new stdClass;
			$arrtmp[$tmp]->header = array();
			$Headers = $xml->xpath("//x:service[x:wsdl = '$wsdl']/x:corex/x:method[@name = '$tmp']/x:header");
			foreach($Headers as $header) $arrtmp[$tmp]->header[] = (string) $header;
			$arrtmp[$tmp]->grant = array();
			$Grants = $xml->xpath("//x:service[x:wsdl = '$wsdl']/x:corex/x:method[@name = '$tmp']/x:grant");
			foreach($Grants as $grant) $arrtmp[$tmp]->grant[] = (string) $grant;
			$arrtmp[$tmp]->grantall = 0;
			$grantall = $xml->xpath("//x:service[x:wsdl = '$wsdl']/x:corex/x:method[@name = '$tmp']/x:grantall[. = '1']");
			if(count($grantall)) $arrtmp[$tmp]->grantall = ((string) $grantall[0]) * 1;
			else $arrtmp[$tmp]->grantall = 0;
		}
		$this->service->methods = $arrtmp;

		$nodeList = $xpaServices->query("//x:service[x:corex and not(@noplaser)]/@identifier");
		foreach($nodeList as $node) $this->services2serve[] = $node->nodeValue;

		$dir = $xml->xpath("//x:service[x:wsdl = '$wsdl']/x:corex/x:dir");
		$dir = (string) $dir[0];
		if(!chdir($_SERVER['DOCUMENT_ROOT'].$dir)) throw new plaf('Directory Access Denied.', 2122);
		unset($xpaServices);
		unset($xml);
		unset($dom);
	}

	public function get($attrib) { return $this->{$attrib}; }

}//END class PLASER_Corex_init

require 'PLASER/plafBack_class.php';
function exception_handler_plaser($exception) { require 'PLASER/exception_handler.php'; }
set_exception_handler('exception_handler_plaser');

//MAIN Process
$stack = array();
$Serving = new PLASER_Corex_init(); //Service Serving
require 'PLASER/ClientBack.php';
require 'PLASER/Corex.php';
$plaser = new PLASER_Corex();
require 'PLASER/Corex_phpsoap.php';
function plaser_handler() {;} //This empty function is for compatibility with the old code.
_corex_phpsoap($Serving->get('wsdlurl'));
?>
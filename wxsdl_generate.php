<?php
/*****
PLASER_WXSDL_Generate class
*****/

class PLASER_WXSDL_Generate
{
	private $wsdlroot;
	private $soaproot;
	private $xsdconfigfile = 'PLASER/schemas/conf.xsd';
	private	$nsconf = 'http://configuration.plaser/schemas';
	private $wsdl_list = array();
	private $xsd_list = array();

	function __construct()
	{
		$this->wsdlroot = $GLOBALS['plaser_conf']->get('wsdlAccessRoot');
		$this->soaproot = $GLOBALS['plaser_conf']->get('soapAccessRoot');
		$configfile = $GLOBALS['plaser_conf']->get('Services');
		$dom = new DOMDocument();
		if(!file_exists($configfile)) die("Missing configuration file: $configfile");
		ini_set('track_errors', '1');
		if(!$dom->load($configfile)) die("Failed to load configuration file: $configfile: $php_errormsg");
		if(!($xsdconfig = file_get_contents($this->xsdconfigfile, true))) die("Failed to read configuration schema: $configfile");
		if(!$dom->schemaValidateSource($xsdconfig)) die("Failed in Schema Validate: $configfile: $php_errormsg");
		ini_set('track_errors', '0');
		unset($xsdconfig);
		$xml = simplexml_import_dom($dom);
		$server = $GLOBALS['plaser_conf']->get('CorexServerDefault');
		$xml->registerXPathNamespace('x', $this->nsconf);

		$rootdir = $GLOBALS['plaser_conf']->get('wsdlFileSystemLocation');
		if(strlen($rootdir) < 5) die('The wsdlFileSystemLocation configuration is too short in PLASER_CONF/plaser_conf.php');
		if(strlen($this->wsdlroot) < 5) die('The wsdlroot configuration is too short');
		$tmp = $this->wsdlroot;
		if(substr($this->wsdlroot, -1) == '/') $tmp = substr($this->wsdlroot, 0, -1);
		$rootdir = $rootdir.$tmp;
		if(file_exists($rootdir)) $this->delchild($rootdir);

		$flag = true;
		foreach($xml->xpath('/x:plaser/x:service[x:corex and not(@noplaser)]') as $xservice)
		{
			if($flag)
			{
				if(!stristr($server, 'http')) die('For Services with Corex the CorexServerDefault has to be defined in PLASER_CONF/plaser_conf.php');
				if(substr($server, -1) == '/') $server = substr($server, 0, -1);
				$flag = false;
			}
			$identifier = (string) $xservice['identifier'];
			$wsdlurl = (string) $xservice->wsdl;
			if(substr_compare($wsdlurl, $this->wsdlroot, 0, strlen($this->wsdlroot)))
				die("For Services with Corex the wsdl element must begin with: {$this->wsdlroot}");
			if(substr($wsdlurl, -5) != '.wsdl') die('The wsdl element has to end with: .wsdl');
			$path = '';
			if(strlen(pathinfo($wsdlurl, PATHINFO_DIRNAME)) > 1) $path = pathinfo($wsdlurl, PATHINFO_DIRNAME);
			$service = pathinfo($wsdlurl, PATHINFO_FILENAME);
			if(strcmp(strtoupper($identifier), strtoupper($service)))
				die('The service identifier and the name of the resource must be similar.');
			$namespace = trim((string) $xservice->corex->namespace);
			if(!$namespace) $namespace = $service;
			$pathsoap = substr_replace($path, $this->soaproot, 0, strlen($this->wsdlroot));
			if(substr($pathsoap, -1) == '/') $pathsoap = substr($pathsoap, 0, -1);
			$methods = $xservice->corex->method;
			$headers = array();
			foreach($methods as $method)
				foreach($method->header as $header) $headers[] = (string) $header;
			$headers = array_unique($headers);
			$wsdl = "";

$wsdl.= <<<WSDL
<?xml version="1.0" encoding="utf-8"?>
<definitions name="$identifier"
	targetNamespace="http://biz.plaser/$namespace/definitions"
	xmlns:tns="http://biz.plaser/$namespace/definitions"
	xmlns:xsd1="http://biz.plaser/$namespace/schemas"

WSDL;

			if(count($headers))
			{
$wsdl.= <<<WSDL
	xmlns:xsdh1="http://headers.plaser/schemas"

WSDL;
			}

$wsdl.= <<<WSDL
	xmlns:soap12="http://schemas.xmlsoap.org/wsdl/soap12/"
	xmlns="http://schemas.xmlsoap.org/wsdl/">

	<types>
		<schema xmlns="http://www.w3.org/2001/XMLSchema">
			<import namespace="http://biz.plaser/$namespace/schemas" schemaLocation="$server$path/$service.xsd"/>

WSDL;

			if(count($headers))
			{
$wsdl.= <<<WSDL
			<import namespace="http://headers.plaser/schemas" schemaLocation="$server/headers.xsd"/>

WSDL;
			}

$wsdl.= <<<WSDL
		</schema>
	</types>

WSDL;

			foreach($methods as $method)
			{
				$name = $method['name'];
$wsdl.= <<<WSDL

	<message name="{$name}Input">
		<part name="body" element="xsd1:{$name}Request"/>
	</message>
	<message name="{$name}Output">
		<part name="body" element="xsd1:{$name}Response"/>
	</message>

WSDL;
			}

			if(count($headers))
			{
				foreach($headers as $header)
				{
$wsdl.= <<<WSDL

	<message name="soapheader_{$header}Input">
		<part name="$header" element="xsdh1:$header"/>
	</message>

WSDL;
				}
			}

$wsdl.= <<<WSDL

	<portType name="{$identifier}_PortType">

WSDL;

			foreach($methods as $method)
			{
				$name = $method['name'];
$wsdl.= <<<WSDL
		<operation name="$name">
			<input message="tns:{$name}Input"/>
			<output message="tns:{$name}Output"/>
		</operation>

WSDL;
			}

$wsdl.= <<<WSDL
	</portType>

	<binding name="{$identifier}_SoapBinding" type="tns:{$identifier}_PortType">
		<soap12:binding style="document" transport="http://schemas.xmlsoap.org/soap/http"/>

WSDL;

			foreach($methods as $method)
			{
				$name = $method['name'];
$wsdl.= <<<WSDL
		<operation name="$name">
			<input>
				<soap12:body use="literal"/>

WSDL;
				foreach($method->header as $header)
				{
$wsdl.= <<<WSDL
				<soap12:header message="tns:soapheader_{$header}Input" part="$header" use="literal"/>

WSDL;
				}
$wsdl.= <<<WSDL
			</input>
			<output>
				<soap12:body use="literal"/>
			</output>
		</operation>

WSDL;
			}

$wsdl.= <<<WSDL
	</binding>

	<service name="{$identifier}_Service">
		<documentation>$identifier service</documentation>
		<port name="{$identifier}_Port" binding="tns:{$identifier}_SoapBinding">
			<soap12:address location="$server$pathsoap/$service"/>
		</port>
	</service>

</definitions>

WSDL;

			$tmp = substr_replace($wsdlurl, '', 0, strlen($this->wsdlroot));
			if(substr($tmp, 0, 1) != '/') $tmp = '/'.$tmp;
			$wsdlfile = $rootdir.$tmp;
			$dir = pathinfo($wsdlfile, PATHINFO_DIRNAME);
			if(!file_exists($dir)) mkdir($dir, 0777, true);
			file_put_contents($wsdlfile, $wsdl);
			$this->wsdl_list[] = $wsdlfile;
			$xsd = $this->xsdgenerate($xservice->corex, $service, $namespace);
			$xsdfile = substr($wsdlfile, 0, -5).'.xsd';
			file_put_contents($xsdfile, $xsd);
			$this->xsd_list[] = $xsdfile;
		}
	}

	private function xsdgenerate($corex, $service, $namespace)
	{
		$realdir = trim((string) $corex->dir);
		if(substr($realdir, 0, 1) != '/') $realdir = '/'.$realdir;
		if(substr($realdir, -1) != '/') $realdir = $realdir.'/';
		$xsdfile = trim((string) $corex->xsd_template);
		if($xsdfile)
		{
			if(!parse_url($xsdfile, PHP_URL_SCHEME)) $xsdfile = $GLOBALS['plaser_conf']->get('CorexDocumentRoot').$xsdfile;
		}
		else $xsdfile = $GLOBALS['plaser_conf']->get('CorexDocumentRoot').$realdir.$service.'.xsd';

		$xsd = new DOMDocument();
		$xsd->preserveWhiteSpace = false;
		$xsd->load($xsdfile);
		$xsdroot = $xsd->documentElement;
		$xpath = new DOMXPath($xsd);
		$xpath->registerNamespace('x', 'http://www.w3.org/2001/XMLSchema');
		foreach($xpath->query("//comment()") as $tmp) $tmp->nodeValue = ' '; //Empty the comments

		$corex->registerXPathNamespace('x', $this->nsconf);
		$methods = $corex->xpath("./x:method");
		$query = "";
		foreach($methods as $method) $query.= "@name='".(string)$method['name']."Request' or @name='".(string)$method['name']."Response' or ";
		$query = substr($query, 0, -4);
		$elements = $xpath->query("/x:schema/x:element[not($query)]");
		foreach($elements as $element) $xsdroot->removeChild($element);

		return str_replace('plaser_schemas', "http://biz.plaser/$namespace/schemas", $xsd->saveXML());
	}

	private function delchild($f)
	{
		if(is_dir($f))
		{
			foreach(glob($f.'/*') as $sf)
			{
				if(is_dir($sf) && !is_link($sf))
				{
					$this->delchild($sf);
					if(file_exists($sf)) rmdir($sf);
				} else unlink($sf);
			}  
		}
	}

	public function get_wsdl_list() { return $this->wsdl_list; }
	public function get_xsd_list() { return $this->xsd_list; }

}//END PLASER_WXSDL_Generate class
?>

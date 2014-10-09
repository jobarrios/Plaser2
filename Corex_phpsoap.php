<?php
/*****
Included by Corex_init.php
PHP-SOAP implementation
*****/

class plaser_handler_class
{

	function usertoken($token)
	{
		$GLOBALS['plaser']->_set_token_user($token);
	}

	function trust($vartrust)
	{
		$GLOBALS['plaser']->_set_trust($vartrust);
	}

	function __call($operation, $args)
	{
		GLOBAL $plaser;
		GLOBAL $soap_server;

		if(is_array($args) && (count($args) == 1)) $args = $args[0];
		$plaser->args = &$args;
		$plaser->_init($operation);
		unset($operation);
		//if($plaser->log_access && ($plaser->get_method() != 'log_ins')) $plaser->log('Access', 200);
		require_once 'PLASER/xsd.php';
		$xsd = new XSD($GLOBALS['Serving']->get('wsdlurl'));
		if(!$xsd->validatesoap($plaser->xml()))
		{
			$tmp = 'XSD_Validate: in request (input) to: '.$plaser->get_target();
			if($xsd->last_error) $tmp.= ' : ' . $xsd->last_error;
			throw new plaf($tmp, 1751, null, 'Client', false);
		}
		unset($xsd);

		//BEGIN Execute the real method
		if(!file_exists($plaser->get_method().'.php'))
			throw new plaf('Missing the file of the requested method: '.$plaser->get_method().'.php', 2003);
		else { return require($plaser->get_method().'.php'); }
		/*
		else //This is for return globals headers
		{
			$returning = require($plaser->get_method().'.php');
			$soap_server->addSoapHeader(new SoapHeader('http://headers.plaser/schemas', 'quota', ''));
			return $returning;
		}
		*/
		//END
	}
}

$soap_server = null;

function _corex_phpsoap($wsdlurl)
{
	GLOBAL $soap_server;

	$options = array();
	$options['features'] = SOAP_SINGLE_ELEMENT_ARRAYS; //http://bugs.php.net/bug.php?id=36226
	$options['soap_version'] = SOAP_1_1;
	//$options['soap_version'] = SOAP_1_2;
	if(!$GLOBALS['plaser_conf']->get('Development_Server')) $options['send_errors'] = false; //bugs.php.net/bug.php?id=42214
	//$options['encoding'] = 'ISO-8859-1'; //If change this also change the same option in ClientBack.php
	$soap_server = new SoapServer($wsdlurl, $options);
	$soap_server->setClass('plaser_handler_class');
	$soap_server->handle();
}
?>

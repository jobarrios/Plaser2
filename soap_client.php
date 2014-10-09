<?php
/*****
PLASER_ClientBack class
----------

ChangeLog
==========
Ver 1.23
- Add the method addheader().
- Add a 3rd parameter, options, to the __construct().
Ver 1.24
- The config file: 'confplaser_client.xml' could be in:
$_SERVER['DOCUMENT_ROOT'].'/sys/confplaser_client.xml' as a general configuration
Ver 1.25
- There is a self-encoding to utf-8 so the SOAP request doesn't need to be encoding
Ver 1.26
- Fixed read_conf() about return erros
- Removed the private function fault(), now the errors are handle by throw new plaf()
- Moved read_conf() to Client_common
Ver 1.27
- Added errors plaser configuraton on call function
Ver 1.28
- Added the public property output_headers for the headers on the response
Ver 1.29
- Added plaser trusting mechanism in the SOAP header
----------
***/

class soap_client
{
	//PUBLICS
	public $debug;
	public $soap; //SoapClient Object
	public $nsheaders; //SOAP header namespace
	public $service;
	public $wsdlurl;
	public $dovalidate = false;
	public $output_headers;
	public $iferror_eval;

	//PRIVATES
	private $wsdl; //Compile WSDL Object
	private $xsd;
	private $noplaser = true;
	private $headers = array();

	//CONSTRUCTOR
	function __construct($service=null, $is_url=false, $options=null)
	{
		if($service == 'PAYMENT')
		{
			require_once 'PLASER/stack_server.php';
		}


		if(!$service) throw new plaf('Error instancing the PLASER_ClientBack class, service is missing.', 170);
		$this->service = $service;
		include 'PLASER/config.php';
		$this->nsheaders = $nsheaders; //SOAP header namespace
		if($is_url) $this->wsdlurl = $service;
		else
		{
			if(!$tmp = $GLOBALS['plaser']->get_clientservices($service))
			throw new plaf("Missing service: $service in services.xml.", 174);
			$this->wsdlurl = $tmp->wsdl;
			$this->noplaser = $tmp->noplaser;
		}

		//SOAP options
		$options = (array) $options;
		$options['trace'] = 1;
		$options['features'] = SOAP_SINGLE_ELEMENT_ARRAYS; //http://bugs.php.net/bug.php?id=36226
		//$options['encoding'] = 'ISO-8859-1';
		if(!isset($options['soap_version'])) $options['soap_version'] = SOAP_1_1;

		try { $this->soap = new SoapClient($this->wsdlurl, $options);
		}
		catch (SoapFault $fault) {
			throw new plaf("SoapClient instance: {$fault->faultcode} : {$fault->faultstring}", 171);
		}

		if(!$this->noplaser)
		{
			$tmp = session_id();
			if(!empty($tmp)) $this->soap->__setCookie(session_name(), $tmp);

			require_once 'PLASER/wsdl.php';
			$this->wsdl = new WSDL($this->wsdlurl);
			$this->service = $this->wsdl->get_service()->identifier; //The real identifier
			$this->dovalidate = true;
			//XSD
			if($GLOBALS['plaser_conf']->get('XSD_Validate_Output'))
			{
				require_once 'PLASER/xsd.php';
				$this->xsd = new XSD($this->wsdlurl);
			}
		}
	}

	static function __callStatic($method, $args) {
		debug($method, 1);
		debug($args, 1);
	}

	function __call($method, $args)
	{
		if(isset($args[0])) $query = &$args[0];
		else $query = null;
		if(isset($args[1])) $func = &$args[1];
		else $func = 0;
		if(is_string($func)) $func = strtolower($func);
		switch($func)
		{
			case -1: case 'n':  return $this->call($method, $query); break;
			case  1: case 'bx': return $this->callBx($method, $query); break;
			//case  2: case 'c':  return $this->callC($method, $query); break;
			case  3: case 'd':  return $this->callD($method, $query); break;
			default: return $this->callB($method, $query); //since 0,'B',null or anything else
		}
	}

	// REDEFINICION DE CALL()
	function call($method, $query)
	{

		if($this->service == 'PAYMENT')
		{
			$ret = require($method.'.php');
			//return require($method.'.php');
		}

		//BEGIN Block Plaser Soap Header
		if(!$this->noplaser)
		{
			if($this->wsdl->isset_header($method, 'usertoken'))
			$this->headers[] = new SoapHeader($this->nsheaders, 'usertoken', $GLOBALS['plaser']->get_token_user(), false);

			if($this->wsdl->isset_header($method, 'trust'))
			{
				if(!$cert = $GLOBALS['plaser_conf']->get('Trust_CertPublic_ClientBack')) throw new plaf('Access Denied, Trust is not allowed in ClientBack.', 172);
				$tmp = '';
				if($this->wsdl->isset_header($method, 'usertoken')) $tmp = $GLOBALS['plaser']->get_token_user();
				$tmp.= ':'. $this->service .':'. $method;
				$publickey = openssl_get_publickey('file://'.$cert);
				openssl_public_encrypt($tmp, $crypted , $publickey);
				openssl_free_key($publickey);
				$this->headers[] = new SoapHeader($this->nsheaders, 'trust', base64_encode($crypted), false);
			}
		}
		//END Block Plaser Soap Header

		if($this->debug > 0)
		{
			try { $this->soap->__soapCall($method, array($query), null, $this->headers, $this->output_headers);
			}
			catch (SoapFault $sf) {
				;
			}
			$this->_debug($this->debug);
			exit;
		}
		else
		{
			try { $ret = $this->soap->__soapCall($method, array($query), null, $this->headers, $this->output_headers);
			}
			catch (SoapFault $sf)
			{
				if($this->iferror_eval) eval($this->iferror_eval);
				$this->iferror_eval = ''; //Reset
				$faultstring = @$sf->faultstring;
				$faultcode = @$sf->faultcode;
				$faultactor = @$sf->faultactor;
				$detail = @$sf->detail;

				if($GLOBALS['plaser_conf']->get('Error_return_level_faultactor'))
				{
					if($faultactor) $faultactor.= ' | '.$GLOBALS['plaser']->get_target();
					else $faultactor = $this->wsdlurl.'::'.$method.' | '.$GLOBALS['plaser']->get_target();
				}
				throw new plaf($detail, $faultstring, $faultactor, $faultcode, false);
			}
			if($GLOBALS['plaser_conf']->get('XSD_Validate_Output') && !$this->noplaser && $this->dovalidate)
			{
				if(!$this->xsd->validatesoap($this->xml()))
				{
					$tmp = "XSD_Validate: in response (output) from: {$this->service}.{$method}";
					if($this->xsd->last_error) $tmp.= ' : ' . $this->xsd->last_error;
					throw new plaf($tmp, 1701, null, 'Server', false);
				}
			}
			return $ret;
		}
	}

	function callB($metodo, $query, $opciones=null)
	{
		GLOBAL $plaser;

		$retorno = $this->call($metodo, $query, $opciones);
		if(is_soap_fault($retorno))
		{
			if($this->iferror_eval) eval($this->iferror_eval);
			$faultstring = isset($retorno->faultstring)?$retorno->faultstring:'';
			$faultcode = isset($retorno->faultcode)?$retorno->faultcode:'';
			$faultactor = isset($retorno->faultactor)?$retorno->faultactor:'';
			$detail = isset($retorno->detail)?$retorno->detail:'';

			if($metodo == 'log_ins')
			{
				error_log('Failure to access "'.$metodo.'", breaking loop. Return: '.print_r($retorno, TRUE));
				echo 'System Fatal Error.';
				exit;
			}
			$plaser->fault($detail, $faultstring, $faultactor, $faultcode);
		}
		$this->iferror_eval = ''; //Reset
		return $retorno;
	}

	function callBx($metodo, $query, $opciones=null)
	{
		$this->trace = 1;
		$this->callB($metodo, $query, $opciones);
		return $this->xml();
	}

	//XML Response
	public function xml()
	{
		return $this->soap->__getLastResponse();
	}

	//XML Request
	public function xmlq()
	{
		return $this->soap->__getLastRequest();
	}

	//Add SOAP Header
	//This method has the same argumets as SoapHeader()
	public function addheader($namespace, $name, $data=null, $mustUnderstand=false, $actor=null)
	{
		if($actor) $this->headers[] = new SoapHeader($namespace, $name, $data, $mustUnderstand, $actor);
		else $this->headers[] = new SoapHeader($namespace, $name, $data, $mustUnderstand);
	}

	public function get_xsd()
	{
		if($this->noplaser) return null;
		if(!$this->xsd)
		{
			require_once 'PLASER/xsd.php';
			$this->xsd = new XSD($this->wsdlurl);
		}
		return $this->xsd->get_xsd();
	}

	private function _debug($val=null)
	{
		if($val == 1) throw new plaf(base64_encode($this->xmlq()), -92, $GLOBALS['plaser']->get_target(), 'Server', false);
		elseif($val == 2) throw new plaf(base64_encode($this->xml()), -93, $GLOBALS['plaser']->get_target(), 'Server', false);
		elseif($val == 3)
		throw new plaf(base64_encode('<?xml version="1.0" encoding="UTF-8"?>'."\n<soap>\n<Request>".$GLOBALS['plaser']->strip_XMLheader($this->xmlq())."\n</Request>\n<Response>".$GLOBALS['plaser']->strip_XMLheader($this->xml())."\n</Response>\n</soap>"), -94, $GLOBALS['plaser']->get_target(), 'Server', false);
	}

}//END class soap_client
?>
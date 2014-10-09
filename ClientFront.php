<?php

class PLASER_ClientFront
{
	//PUBLICS
	public $debug; //Allow 0,1,2,3
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
		if(!$service) throw new plaf('Error instancing the PLASER_ClientFront class, service is missing.', 150);
		$this->service = $service;
		include 'PLASER/config.php';
		$this->nsheaders = $nsheaders; //SOAP header namespace
		if($is_url) $this->wsdlurl = $service;
		else
		{
			if(!$tmp = $GLOBALS['plaser']->get_clientservices($service))
				throw new plaf("Missing service: $service in services.xml.", 154);
			$this->wsdlurl = $tmp->wsdl;
			$this->noplaser = $tmp->noplaser;
		}

		//SOAP options
		$options = (array) $options;
		$options['trace'] = 1;
		$options['features'] = SOAP_SINGLE_ELEMENT_ARRAYS; //http://bugs.php.net/bug.php?id=36226
		//$options['encoding'] = 'ISO-8859-1';
		if(!isset($options['soap_version'])) $options['soap_version'] = SOAP_1_1;

		try { $this->soap = new SoapClient($this->wsdlurl, $options); }
		catch (SoapFault $fault) {throw new plaf("SoapClient instance: {$fault->faultcode} : {$fault->faultstring}", 151);}

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
			case  1: case 'bx': return $this->callx($method, $query); break;
			case  2: case 'c':  return $this->callC($method, $query); break;
			case  3: case 'd':  return $this->callD($method, $query); break;
			default: return $this->call($method, $query); //0,null or anything else
		}
	}

	function call($method, $query, $output=true)
	{
		//BEGIN Block Plaser Soap Header
		if(!$this->noplaser)
		{
			if($this->wsdl->isset_header($method, 'usertoken'))
				$this->headers[] = new SoapHeader($this->nsheaders, 'usertoken', $GLOBALS['plaser']->get_token_user(), false);

			if($this->wsdl->isset_header($method, 'trust'))
			{
				if(!$cert = $GLOBALS['plaser_conf']->get('Trust_CertPublic_ClientFront')) throw new plaf('Access Denied, Trust is not allowed in ClientFront.', 152);
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
			try { $this->soap->__soapCall($method, array($query), null, $this->headers, $this->output_headers); }
			catch (SoapFault $sf) {;}
			$this->_debug($this->debug);
			exit;
		}
		else
		{
			try
			{
				if(!$output) $this->soap->__soapCall($method, array($query), null, $this->headers, $this->output_headers);
				else $ret =  $this->soap->__soapCall($method, array($query), null, $this->headers, $this->output_headers);
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
					if($faultactor) $faultactor.= ' | '.$_SERVER['PHP_SELF'];
					else $faultactor = $this->wsdlurl.'::'.$method.' | '.$_SERVER['PHP_SELF'];
				}
				throw new plaf($detail, $faultstring, $faultactor, $faultcode, false);
			}
			if($GLOBALS['plaser_conf']->get('XSD_Validate_Output') && !$this->noplaser && $this->dovalidate)
			{
				if(!$this->xsd->validatesoap($this->xml()))
				{
					$tmp = "XSD_Validate: in response (output) from: {$this->service}.{$method}";
					if($this->xsd->last_error) $tmp.= ' : ' . $this->xsd->last_error;
					throw new plaf($tmp, 1801, $_SERVER['PHP_SELF'], 'Server_SOAP', false);
				}
			}
			if($output) return $ret;
		}
	}

	function callx($method, $query)
	{
		$this->call($method, $query, false);
		return $this->xml();
	}

	function callC($method, $query)
	{
		$varses = $this->component.'_'.$method;
		if(isset($_SESSION['CACHE_SOAP'][$varses]))
		{
			if($this->debug > 0)  die('SOAP CACHE:'."\n".$_SESSION['CACHE_SOAP'][$varses]);
		}
		else
		{
			$this->call($method, $query);
			$_SESSION['CACHE_SOAP'][$varses] = $this->xml();
		}
		return $_SESSION['CACHE_SOAP'][$varses];
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
		if($val == 1)
		{
			if(headers_sent()) echo '<hr /><pre>'.htmlentities(utf8_decode($this->xmlq())).'</pre>';
			else
			{
				header('Content-type: text/xml');
				echo $this->xmlq();
			}
		}
		elseif($val == 2)
		{
			if(headers_sent()) echo '<hr /><pre>'.htmlentities(utf8_decode($this->xml())).'</pre>';
			else
			{
				header('Content-type: text/xml');
				echo $this->xml();
			}
		}
		elseif($val == 3)
		{
			if(headers_sent()) echo '<hr /><pre>'.htmlentities(utf8_decode(($this->xmlq()).($this->xml()))).'</pre>';
			else
			{
				header('Content-type: text/xml');
				echo '<?xml version="1.0" encoding="UTF-8"?>'."\n<soap>\n<Request>".$GLOBALS['plaser']->strip_XMLheader($this->xmlq())."\n</Request>\n<Response>".$GLOBALS['plaser']->strip_XMLheader($this->xml())."\n</Response>\n</soap>";
			}
		}
		else echo '<br /><b>Error on debug: 0, 1, 2, 3.</b>';
	}

}//END class PLASER_ClientFront
?>

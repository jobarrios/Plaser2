<?php
/*****
PLASER_Corex: Main class of the soap server side
*****/

class PLASER_Corex
{
	//PUBLICS
	public $args; //Maybe this should be private and implement a get method

	//PRIVATES
	private $service;
	private $method;
	private $target;
	private $idy = 0; //User authenticated Type
	private $idx = -1; //User authenticated Index
	private $idz = 0; //User authenticated Dependent Account
	private $token_user = null; //Soap Header plaser_token_user
	private $trust = null; //Soap Header plaser_trust
	private $user_login = null; //string user Login=Username
	private $user_fullname = null; //string user Full Name
	private $user_id = null; //string PLASER user ID, the unique ID from User Management service
	private $buf_user_services = array(); //Array of user restrained services
	private $ClientServices = null;
	private $cache_time = 10; //In minutes
	//Static Configuration
	private $SAAA_services = array('INTERNAL_SAAA','TREE','MYACCOUNT','ACCOUNT','SAAA','ADM_SYS');

	function __construct()
	{
		$this->service = $GLOBALS['Serving']->get('service')->identifier;
	}

	public function _init($method)
	{
		if(!isset($GLOBALS['Serving']->get('service')->methods[$method])) throw new plaf('ACCESS DENIED, Invalid Method.', 302);
		$this->method = $method;
		$this->target = $this->service . '.' . $this->method;

		$headers = $GLOBALS['Serving']->get('service')->methods[$method]->header;
		if(count($headers))
		{
			if(in_array('usertoken', $headers))
			{
				if(!$this->token_user)
					throw new plaf('Sorry, your session has expired or you are not authenticated in the system.', 303, null, 'Client');
				if(strlen($this->token_user) != 32 || !ctype_xdigit($this->token_user)) //no a valid MD5
					throw new plaf('Invalid SOAP Header: usertoken.', 304, null, 'Client');
			}
			else $this->token_user = null; //Clean up

			if(in_array('trust', $headers))
			{
				if(!$this->trust) throw new plaf('The {trust} soap header is required.', 305, null, 'Client');
				if(strlen($this->trust) < 50 || strlen($this->trust) > 1024) throw new plaf('Invalid SOAP Header: trust.', 306, null, 'Client');
			}
			else $this->trust = null; //Clean up

			$q = new stdClass;
			$q->token_user = $this->token_user;
			$q->trust = $this->trust;
			$q->service = $this->service;
			$q->method = $this->method;
			if($this->token_user)
			{
				$make_call = true;
				session_start();
				if (isset($_SESSION['_PLASER_Corex_user_rights'])) {
					$time = $_SESSION['_PLASER_Corex_user_rights_time'];
					if ( ((time()-$time) / 60) < $this->cache_time ) {
						$ret = $_SESSION['_PLASER_Corex_user_rights'];
						$make_call = false;
					}
				}
				if ($make_call) {
					session_write_close();
					$client = new PLASER_ClientBack('INTERNAL_SAAA');
					$ret = $client->user_rights($q);
					unset($client);
					$time = time();
					session_start();
					$_SESSION['_PLASER_Corex_user_rights_time'] = $time;
					$_SESSION['_PLASER_Corex_user_rights'] = $ret;
					$make_call = false;
				}
				session_write_close();
				if($this->trust) $this->trust_chk($q); //Checking trust

				if (in_array($this->service, $ret->system_services->item)) //Service Restrained
				{
					$grant = false;
					if (in_array($this->service, $ret->user_services->item)) $grant = true;
					elseif ($GLOBALS['plaser_conf']->get('Trust_Static_Enable'))
					{
						$trustxml = new DOMDocument();
						$trustxml->load($GLOBALS['plaser_conf']->get('Services'));
						$tt = new DOMXPath($trustxml);
						$tt->registerNamespace('x', $GLOBALS['Serving']->get('ns'));
						if( $tt->evaluate("
								(/x:plaser/x:grantall[. = '1']) or
								(//x:service[@identifier = '$this->service']/x:corex/x:grantall[. = '1']) or
								(//x:service[@identifier = '$this->service']/x:corex/x:method[@name = '$this->method']/x:grantall[. = '1'])")
						) $grant = true;
						else
						{
							$tmp = $tt->query("//x:service[@identifier = '$this->service']/x:corex/x:method[@name = '$this->method']/x:grant");
							foreach($tmp as $item) if(in_array($item->nodeValue, $ret->user_services->item)) {
								$grant = true; break;
							}
						}
					}
					if(!$grant)
					{
						$this->log('Authorization Denied "'.$this->service.'.'.$this->method.'"', 503, null, null, null, null, null, $this->token_user);
						throw new plaf('You do not have the permission to perform this action, please contact system administrator.', 503);
					}
				}
				//Else => Service Limited but not Restrained

				$this->idy = $ret->idy;
				$this->idx = $ret->idx;
				$this->idz = $ret->idz;
				$this->user_id = $ret->iduser;
				$this->user_login = $ret->login;
				$this->user_fullname = $ret->fullname;
				if(isset($ret->user_services->item)) $this->buf_user_services = $ret->user_services->item;
			}
			else $this->trust_chk($q);
		}
		else //Clean up
		{
			$this->token_user = null;
			$this->trust = null;
		}
	}

	public function get_idy() { return $this->idy; }

	public function get_idx() { return $this->idx; }

	public function get_idz() { return $this->idz; }

	public function get_token_user() { return $this->token_user; }

	public function get_user_login() { return $this->user_login; }

	public function get_user_fullname() { return $this->user_fullname; }

	public function get_user_id() { return $this->user_id; }

	public function get_accid() { return $this->user_id; }

	public function get_service() { return $this->service; }

	public function get_method() { return $this->method; }

	public function get_target() { return $this->target; }

	//Get the real assigned modules for this user, it does not use ICS - Integral Contextual Security
	public function get_services() { return $this->buf_user_services; }

	public function get_mods() { return $this->get_services(); } //alias for get_services()

	//Check for a real assigned module for this user, it does not use ICS - Integral Contextual Security
	public function isset_service($svc) { return in_array($svc, $this->buf_user_services); }

	public function isset_mod($svc) { return $this->isset_service($svc); } //alias for isset_service()

	//public function is_trusting() { return ($this->trust)?true:false; } //Never used and could be a problem with the serialization

	public function view($var)
	{
		throw new plaf(print_r($var,true), -90, $this->target, 'Server', false);
	}

	//do log
	public function log($desc=null, $code=null, $mod=null, $compo=null, $met=null, $source=null, $ip=null, $tokenuser=null, $exception=false)
	{
		$q = new stdClass;
		$q->desc = $desc;
		$q->code = $code;
		//$q->modulo = $mod?$mod:$this->module;
		$q->modulo = $mod?$mod:'';
		$q->componente = $compo?$compo:$this->service;
		$q->metodo = $met?$met:$this->method;
		$q->source = $source?$source:'BACKEND';
		if(strtoupper(substr($q->source, 0, 7)) != 'BACKEND') $q->source = 'BACKEND '.$q->source;
		$q->ip = $ip?$ip:$_SERVER['REMOTE_ADDR'];
		//$q->tokenuser = $tokenuser?$tokenuser:null;
		$q->tokenuser = $tokenuser?$tokenuser:$this->token_user;

		if(in_array($this->service, $this->SAAA_services))
		{
			require_once '_log_ins.php';
			_log_ins($q->desc, $q->code, $q->modulo, $q->componente, $q->metodo, $q->source, $q->ip, $q->tokenuser, $exception);
		}
		else
		{
			$client = new PLASER_ClientBack('INTERNAL_SAAA');
			$client->log_ins($q);
			unset($client);
		}
	}

	public function xml()
	{
		return $GLOBALS['HTTP_RAW_POST_DATA'];
	}

	public function _set_token_user($token)
	{
		$this->token_user = $token;
	}

	public function _set_trust($vartrust)
	{
		$this->trust = $vartrust;
	}

	private function ClientServices_init()
	{
		require 'PLASER/ClientServices.php';
		$tmp = new PLASER_ClientServices();
		$this->ClientServices = $tmp->_init($GLOBALS['plaser_conf']->get('Services'));
		unset($tmp);
	}

	public function get_clientservices($service)
	{
		if(!$this->ClientServices) $this->ClientServices_init();
		if(!isset($this->ClientServices[$service])) return null;
		return $this->ClientServices[$service];
	}

	public function get_storageID()
	{
		return trim($GLOBALS['Serving']->get('service')->storageID);
	}

	public function _getvars() {
		$toinclude = array('args', 'service', 'method', 'target', 'token_user', 'trust');
		$tmp = array();
		foreach($this as $key => $value) {
			if (in_array($key, $toinclude)) $tmp[$key] = $value;
		}
		return $tmp;
	}

	private function trust_chk($q) {
		if(!$cert = $GLOBALS['plaser_conf']->get('Trust_CertPrivate_Corex'))
		{
			$this->log('Receiving Trust but Denied by configuration: "'.$q->service.'.'.$q->method.'"', 506, null, null, null, null, null, $q->token_user);
			throw new plaf('ACCESS DENIED, Trust is not allowed in Corex.', 506);
		}
		$privatekey = openssl_get_privatekey('file://'.$cert);
		$res_ssl = openssl_private_decrypt(base64_decode($q->trust), $trust_val, $privatekey);
		openssl_free_key($privatekey);
		if(!$res_ssl)
		{
			$this->log('Trust Denied "'.$q->service.'.'.$q->method.'"', 507, null, null, null, null, null, $q->token_user);
			throw new plaf('ACCESS DENIED.', 507);
		}
		$trust_arr = explode(':', $trust_val);
		if( ($trust_arr[0] != $q->token_user) || ($trust_arr[1] != $q->service) || ($trust_arr[2] != $q->method) )
		{
			$this->log('Trust Denied "'.$q->service.'.'.$q->method.'"', 508, null, null, null, null, null, $q->token_user);
			throw new plaf('ACCESS DENIED.', 508);
		}
		return null;
	}

	//MISC Functions
	public function strip_XMLheader($xml)
	{
		$xml = trim($xml);
		return substr($xml, strpos($xml, '?>') + 2);
	}

}//END class PLASER_Corex
?>
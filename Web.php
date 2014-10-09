<?php
/*
 * PLASER_Web, main class of the Web
 */

class PLASER_Web
{
	//PUBLICS
	public $logs_remote = 1;
	public $error_returnxml = 0; //For Ajax

	//PRIVATES
	private $webxml = '/sys/web.xml';
	private $nsheaders; //SOAP header namespace
	private $loginpage = '/';
	private $loginpage_mobile = '/m/';
	private $get2soappage = '/sys/get2soap.php';
	private $soap_login; //loginResponse soap msg
	private $soap_tree_idy; //tree_idyResponse soap msg
	private $idy = 0; //User authenticated Type
	private $idx = 0; //User authenticated Index
	private $idz = 0; //User authenticated Dependent Account
	private $token_user = null;
	//LBTODO Convert the users methods in an object
	private $user_login = null; //string user Login=Username
	private $user_fullname = null; //string user Full Name
	private $user_id = null; //string PLASER user ID, the unique ID from User Management module
	private $nsSAAA; //SAAA services namespace
	private $buf_sysmods;
	private $buf_usermods;
	private $ClientServices = null;

	function __construct()
	{
		if(isset($_SERVER['HTTP_CONTENT_TYPE_RESPONSE']) && $_SERVER['HTTP_CONTENT_TYPE_RESPONSE'] == 'text/xml')
			$this->error_returnxml = 1; //Ajax Request Header

		//BEGIN read Config
		include 'PLASER/config.php';
		$this->nsheaders = $nsheaders; //SOAP Headers namespace, from config.php
		$this->nsSAAA = $nsSAAA;
		//END read Config

		//BEGIN read XML Web Config
		$xml = simplexml_load_file($_SERVER['DOCUMENT_ROOT'].$this->webxml) or die('Error reading Web XML file "'.$this->webxml.'".');
		$this->buf_sysmods = $xml->modules;
		$this->logs_remote = (int) $xml->logs_remote;
		//END read XML Web Config

		if($_SERVER['PHP_SELF'] != $this->get2soappage) $this->_load_session();
	}

	public function _chk_permit()
	{
		//LBTODO: In order to this works the web.xml schema must be done
		$cmod = $this->get_sys_cmod();
		$type = $this->get_sys_typemod($cmod);
		if(!$type) $type = 'pub'; //if null then 'pub'
		if($type != 'pub')
		{
			if(!$this->is_authenticated())
			{
				error_log("PLASER_Web: [Client {$_SERVER['REMOTE_ADDR']}] Access Denied: {$_SERVER['PHP_SELF']}");
				if($this->error_returnxml)
				{
					$pf = new plaf('Sorry, your session has expired or you are not authenticated in the system.', 1400);
					$pf->fault(1);
					exit;
				}
				else $this->go('/error.html');
			}
			if($type == 'rtd' && !$this->isset_mod($cmod))
			{
				$permit = false;
				foreach($GLOBALS['plaser_conf']->get('Web_rtd_exempt') as $exempt) if(stristr($_SERVER['PHP_SELF'], $exempt)) { $permit = true; break; }
				if(!$permit) throw new plaf('You do not have the permission to access this resource, please contact the administrator.', 1401);
			}
		}
		//if 'pub' do nothing
	}

	public function get_idy() { return $this->idy; }

	public function get_idx() { return $this->idx; }

	public function get_idz() { return $this->idz; }

	public function get_token_user() { return $this->token_user; }

	public function get_user_login() { return $this->user_login; }

	public function get_user_fullname() { return $this->user_fullname; }

	public function get_user_id() { return $this->user_id; }

	public function get_accid() { return $this->user_id; }

	//This method is public because login_ajax.php
	public function _load_session($sess_id = null)
	{
		if($sess_id) session_id($sess_id);
		session_start();
		$this->soap_login = isset($_SESSION['_PLASER_login'])?$_SESSION['_PLASER_login']:null;
		$this->soap_tree_idy = isset($_SESSION['_PLASER_tree_idy'])?$_SESSION['_PLASER_tree_idy']:null;
		session_write_close();
		if(!$this->soap_login) return false;
		$dom = new DOMDocument();
		$dom->loadXML($this->soap_login);
		$xpath = new DOMXPath($dom);
		$xpath->registerNamespace('x', $this->nsSAAA);
		$this->idy = $xpath->query('//x:idy')->item(0)->nodeValue;
		$this->idx = $xpath->query('//x:idx')->item(0)->nodeValue;
		$this->idz = $xpath->query('//x:idz')->item(0)->nodeValue;
		$this->token_user = $xpath->query('//x:token_user')->item(0)->nodeValue;
		$this->user_login = $xpath->query('//x:usuario')->item(0)->nodeValue;
		$this->user_fullname = $xpath->query('//x:nombre')->item(0)->nodeValue;
		$this->user_id = $xpath->query('//x:idusuario')->item(0)->nodeValue;
		return true;
	}

	public function get_soaplogin() { return $this->soap_login; }

	public function is_authenticated() { return ($this->soap_login)?true:false; }

	public function get_tree_idy()
	{
		if(!$this->is_authenticated()) return false;
		if(!$this->soap_tree_idy)
		{
			$client = new PLASER_ClientFront('TREE');
			$ret = $client->tree_idy(null, 'Bx');
			session_start();
			$_SESSION['_PLASER_tree_idy'] = $ret;
			session_write_close();
			$this->soap_tree_idy = $ret;
		}
		return $this->soap_tree_idy;
	}

	public function view($var)
	{
		throw new plaf(print_r($var,true), -91, $_SERVER['PHP_SELF'], 'PLASER_Web', false);
	}

	public function log($desc=null, $code=null, $mod=null, $compo=null, $met=null, $source=null)
	{
		if(!$this->logs_remote) return false;
		$q = new stdClass;
		$q->desc = $desc;
		$q->code = $code;
		$q->modulo = $mod?$mod:$this->get_cmod();
		$q->componente = $compo?$compo:'';
		$q->metodo = $met?$met:$_SERVER['PHP_SELF'];
		$q->source = $source?$source:'WEB';
		if(strtoupper(substr($q->source, 0, 3)) != 'WEB') $q->source = 'WEB '.$q->source;
		$q->ip = $_SERVER['REMOTE_ADDR'];
		$q->tokenuser = $this->token_user;
		$client = new PLASER_ClientFront('INTERNAL_SAAA');
		$client->log_ins($q);
		unset($client);
	}

	public function get_sys_mods() { return $this->buf_sysmods; }

	//get the current module from system
	public function get_sys_cmod()
	{
		$arr = explode('/', substr($_SERVER['PHP_SELF'], 1));
		foreach($this->get_sys_mods()->item as $mod)
			if($mod->dir == '/'.$arr[0]) return (string) $mod->module;
		return false;
	}

	public function get_sys_typemod($qmod)
	{
		foreach($this->get_sys_mods()->item as $mod)
			if($mod->module == $qmod) return (string) $mod->type;
		return false;
	}

	public function _clear_mods()
	{
		$this->buf_usermods = null;
	}

	//Return a SimpleXML Object
	public function get_mods()
	{
		if($this->buf_usermods) return $this->buf_usermods;
		$q = '';
		if($this->is_authenticated())
		{
			$dom = new DOMDocument();
			$dom->loadXML($this->get_soaplogin());
			$xpath = new DOMXPath($dom);
			$xpath->registerNamespace('x', $this->nsSAAA);
			$str = '';
			foreach($xpath->query('//x:modules_rtd/x:mod') as $Node) $str.= "module='{$Node->nodeValue}' or ";
			$str.= '0';
			$q.= " or type='ltd' or (type='rtd' and ($str))";
		}
		$tmp = '<modules>';
		foreach($this->get_sys_mods()->xpath("//item[type='pub'$q]") as $item) $tmp.= $item->asXML();
		$tmp.= '</modules>';
		$this->buf_usermods = simplexml_load_string($tmp);
		return $this->buf_usermods;
	}

	public function get_typemod($qmod)
	{
		foreach($this->get_mods()->item as $mod)
			if($mod->module == $qmod) return (string) $mod->type;
		return false;
	}

	public function isset_service($svc)
	{
		if($this->get_typemod($svc)) return true;
		else return false;
	}

	public function isset_mod($svc) { return $this->isset_service($svc); } //alias for isset_service()

	//get the current module, a string
	public function get_cmod()
	{
		$arr = explode('/', substr($_SERVER['PHP_SELF'], 1));
		foreach($this->get_mods()->item as $mod)
			if($mod->dir == '/'.$arr[0]) return (string) $mod->module;
		return false;
	}

	//Session reset
	public function reset_sess($sess_id = null)
	{
		if($sess_id) session_id($sess_id);
		session_start();
		$buf = array();
		if(isset($_SESSION['_PLASER_login'])) $buf['_PLASER_login'] = $_SESSION['_PLASER_login'];
		if(isset($_SESSION['_PLASER_tree_idy'])) $buf['_PLASER_tree_idy'] = $_SESSION['_PLASER_tree_idy'];
		$_SESSION = array();
		$_SESSION = (array) $buf;
		session_write_close();
	}

	//Session Destroy
	public function destroy_sess($sess_id = null)
	{
		if($sess_id) session_id($sess_id);
		session_start();
		$_SESSION = array();
		if(isset($_COOKIE[session_name()])) { setcookie(session_name(), '', time()-42000, '/'); }
		session_destroy();
	}

	public function go_loginpage($seturl = false)
	{
		$this->destroy_sess();
		if($this->mobile()->isMobile() && !$this->mobile()->isTablet()) $page = $this->loginpage_mobile;
		else $page = $this->loginpage;
		if($seturl) $page .= "?url=" . base64_encode(@$_SERVER['REQUEST_URI']);
		$this->go($page);
	}

	public function logout()
	{
		$client = new PLASER_ClientFront('SAAA');
		$client->logout(null);
		$this->go_loginpage();
	}

	public function go($url)
	{
		header('Location: '.$url);
		exit;
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

	//MISC Functions
	public function mobile()
	{
		include_once 'Util/Mobile_Detect.php';
		return new Mobile_Detect;
	}

	public function strip_XMLheader($xml)
	{
		$xml = trim($xml);
		return substr($xml, strpos($xml, '?>') + 2);
	}

}//END class PLASER_Web
?>
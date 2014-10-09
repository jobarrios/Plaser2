<?php
/*****

PLASER_Client ---> PLASER_xml ---> PLASER_xslt
=============
PLASER_xslt
-------------
PLASER_xml
-------------
PLASER_Client
=============

$xo = new PLASER_xml(); //Se crea el objeto xml
$client = new PLASER_Client();
$xo->add($client->callBx('Componente1.Metodo1', $query));
...
$client = new PLASER_Client();
$xo->add($client->callBx('Componente2.Metodo2', $query));
...
$xml = $xo->Create_XML();
$xslt = new PLASER_xslt();
$xslt->process($xml);

$xo = new PLASER_xml();
$client = new PLASER_Client();
$xo->xmls[] = $client->callBx('Componente1.Metodo1', $query);
...
$client = new PLASER_Client();
$xo->xmls[] = $client->callBx('Componente2.Metodo2', $query);
...
$xml = $xo->Create_XML(); //  o  $xml = $xo->Create_XML($xo->xmls);
$xslt = new PLASER_xslt();
$xslt->process($xml);

$xo = new PLASER_xml();
$client = new PLASER_Client();
$xslt = new PLASER_xslt();
$xslt->process($xo->Create_XML($client->callBx('Componente.Metodo', $query)));

*****/

require 'PLASER/xml_common.php';

class PLASER_xmlFront extends PLASER_xml_common
{
	// PUBLICS
	public $xmls;
	public $hd_encoding = 'utf-8'; //XML Header encoding directive

	// PRIVATES
	private $_soaplogin;
	private $_treeidy;
	private $_sys;
	private $_mods;
	private $_cmod;

	// CONSTRUCTOR
	function __construct($soaplogin=true, $treeidy=true, $sys=true, $mods=true, $cmod=true)
	{
		$this->xmls = array();

		//SOAP loginResponse
		$this->_soaplogin = "";
		if($soaplogin) $this->_get_soaplogin();

		//SOAP tree_idy
		$this->_treeidy = "";
		if($treeidy) $this->_get_treeidy();

		if($sys && $GLOBALS['plaser']->get_soaplogin())
		{
			$this->_sys = '<SYSTEM xmlns="http://xml.xslt.plaser/system">';
			$this->_sys.= '<sess>'.session_id().'</sess>';
			$this->_sys.= '<get2soap>';
			$this->_sys.= isset($_SERVER['HTTPS'])?'https':'http';
			$this->_sys.= '://'. $_SERVER['SERVER_NAME'] .'/sys/get2soap.php</get2soap>';
			$this->_sys.= '</SYSTEM>';
		}

		//Modules object
		$this->_mods = "";
		if($mods) $this->_get_mods();

		//Current module
		$this->_cmod = "";
		if($cmod) $this->_get_cmod();
	}

	function add($buf)
	{
		$this->xmls[] = $buf;
	}

	function addx($mixed, $elem = null, $utf8e = false, $attrib = null)
	{
		$buf = '';
		$elem = trim($elem);
		if($elem)
		{
			$buf.= '<'.$elem;
			if($attrib)
			{
				$attrib = (array) $attrib;
				foreach($attrib as $att) $buf.= ' '.$att;
			}
			$buf.= '>'.$this->mixed2xml($mixed, $utf8e).'</'.$elem.'>';
		}
		else $buf = $this->mixed2xml($mixed, $utf8e);
		$this->xmls[] = $buf;
	}

	function stripXMLhd($buf)
	{
		$contents = trim($buf);
		$p1 = strpos($contents, '<?');
		$p2 = strpos($contents, '?>');
		if($p2) $contents = substr_replace($contents, '', $p1, $p2 + 2 - $p1);
		return $contents;
	}

	function Create_XML($xml = null, $haveRoot = true)
	{
		$buf_arr = array();
		if($this->_soaplogin) $buf_arr[] = $this->_soaplogin;
		if($this->_treeidy) $buf_arr[] = $this->_treeidy;
		if($this->_sys) $buf_arr[] = $this->_sys;
		if($this->_mods) $buf_arr[] = $this->_mods;
		if($this->_cmod) $buf_arr[] = $this->addx($this->_cmod, 'currentmod');
		if($xml != null)
		{
			if(is_array($xml)) $buf_arr = array_merge($buf_arr, $xml);
			else $buf_arr = array_merge($buf_arr, array($xml));
		}
		else
		{
			if(count($this->xmls)) $buf_arr = array_merge($buf_arr, $this->xmls);
		}
		$buf_str = "";
		foreach($buf_arr as $buff)
		{
			//Strip the XML header if exists
			//if($this->is_utf8($buff)) $buf_str .= "\n".$this->stripXMLhd(utf8_decode($buff));
			//else $buf_str .= "\n".$this->stripXMLhd($buff);
			$buf_str .= "\n".$this->stripXMLhd($buff);
		}
		if ($haveRoot) return "<?xml version=\"1.0\" encoding=\"{$this->hd_encoding}\"?>\n<root>".$buf_str."\n</root>";
		else return $buf_str;
	}

	public function view($contenttype = true)
	{
		if($contenttype) header('Content-type: text/xml');
		echo $this->Create_XML();
		exit;
	}

	function ReplaceTAG($tag, $str, $root)
	{
		$pos1 = strpos($root, "<$tag>");
		$pos2 = strpos($root, "</$tag>");
		if($pos2 != 0)
		{
			$pos1 = $pos1 + 1 + strlen($tag) + 1;
			return substr_replace($root, $str, $pos1 , $pos2 - $pos1);
		}
		else return $root;
	}

	function InsertInTAG($str, $root, $tag)
	{
		$pos = strpos($root, "</$tag>");
		if($pos != 0) return substr_replace($root, $str, $pos)."</$tag>";
		else return $root;
	}

	function DeleteTAG($tag, $XML)
	{
		$pos1 = strpos($XML, "<".$tag." ");
		$pos2 = strpos($XML, "</".$tag.">");
		$str1 = substr($XML, 0, $pos1);
		$str2 = substr($XML, $pos2 + strlen($tag)+3);
		return trim($str1).trim($str2);
	}

	function _get_soaplogin()
	{
		$this->_soaplogin = $GLOBALS['plaser']->get_soaplogin();
	}

	function _get_treeidy()
	{
		$this->_treeidy = $GLOBALS['plaser']->get_tree_idy();
	}

	function _get_mods()
	{
		if(count($GLOBALS['plaser']->get_mods()->xpath('item')))
			$this->_mods = $GLOBALS['plaser']->get_mods()->asXML();
	}

	function _get_cmod()
	{
		$this->_cmod = $GLOBALS['plaser']->get_cmod();
	}

}
?>

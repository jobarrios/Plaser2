<?php

class ProxySOAP
{
	public $url;
	public $scheme;
	public $host;
	public $port;
	public $user;
	public $pass;
	public $path;
	public $query;
	public $fragment;
	public $soapaction;
	public $headers_send;
	public $headers_back;
	public $headers_rx;
	public $payload_rx;
	public $payload_rx_len;
	public $timeout;
	public $php_set_time_limit;
	
	function ProxySOAP( $url )
	{
		$this->url = $url;
		$arr = parse_url($url);
		$this->scheme = isset($arr['scheme'])?$arr['scheme']:'http';
		if(isset($arr['host'])) $this->host = $arr['host'];
		$this->port = isset($arr['port'])?$arr['port']:80;
		if(isset($arr['user'])) $this->user = $arr['user'];
		if(isset($arr['pass'])) $this->pass = $arr['pass'];
		if(isset($arr['path'])) $this->path = $arr['path'];
		if(isset($arr['query'])) $this->query = $arr['query'];
		if(isset($arr['fragment'])) $this->fragment = $arr['fragment'];
		$this->timeout = 30;
		$this->headers_send = array();
		$this->headers_back = array();
		$this->headers_rx = array();
		$this->php_set_time_limit = 150;
	}
	
	function send(&$msg)
	{
		set_time_limit($this->php_set_time_limit);
	
		$head = 'POST '.$this->url.' HTTP/1.0'."\r\n";
		$head.= 'Host: '.$this->host."\r\n";
		$head.= 'User-Agent: ProxySOAP-0.1'."\r\n";
		$head.= 'Content-Type: text/xml; charset=UTF-8'."\r\n";
		$head.= 'Content-Length: '.strlen($msg)."\r\n";
		$head.= 'SOAPAction: "'.$this->soapaction.'"'."\r\n";
		if(sizeof($this->headers_send)) foreach($this->headers_send as $key => $value) $head.= $key.': '.$value."\r\n";
		$head.= "\r\n";
	
		$fp = @fsockopen($this->host,$this->port,$errno,$errstr,$this->timeout);
		if(!$fp) die('ProxySOAP: '.$this->host.':'.$this->port.' SOAPAction: '.$this->soapaction);
		fputs($fp,$head.$msg);
		if(function_exists('socket_set_timeout')) socket_set_timeout($fp, $this->timeout);
		while( ($tmp = trim(fgets($fp, 1024))) != "") $this->headers_rx[] = $tmp;
		while(!feof($fp)) $this->payload_rx .= fgets($fp,1024);
		fclose($fp);
		$this->payload_rx_len = strlen($this->payload_rx);
		return $this->payload_rx_len;
	}
	
	function back($msg=null)
	{
		foreach($this->headers_rx as $val) if(strstr($val, 'Status:')) header($val);
		header('Server: ProxySOAP-0.1');
		if(sizeof($this->headers_back)) foreach($this->headers_back as $key => $value) header($key.': '.$value);
		header('Content-Type: text/xml; charset=UTF-8');
		if($msg != null) $len = strlen($msg);
		else $len = strlen($this->payload_rx);
		header('Content-Length: '.$len);
	
		if($msg != null) echo $msg;
		else echo $this->payload_rx;
	}
	
	function doit(&$msg)
	{
		$this->send($msg);
		$this->back();
	}

}
?>

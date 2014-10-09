<?php

class PLASER_xslt
{
	//PUBLICS
	public $xsl_file; //XSL file
	public $params;
	public $use_params_default = true;

	//PRIVATES
	private $_params = array();

	function __construct()
	{
		$this->xsl_file = basename($_SERVER['PHP_SELF'], '.php').'.xsl';
	}

	public function process($xml=null)
	{
		if(!$xml) die('XSLT: The "XML" argument is required in the "process()" method.');
		$para = array();
		if($this->use_params_default) $para = $this->_params;
		if($this->params) $para = array_merge($para, $this->params);
		if(!array_key_exists('helpfile', $para))
		{
			$tmp = basename($_SERVER['PHP_SELF'], '.php').'_help.html';
			if(file_exists($tmp)) ;
			elseif(file_exists('../general/help/index.html')) $tmp = '../general/help/index.html';
			else $tmp = '';
			$tmparr = array('helpfile' => $tmp);
			$para = array_merge($para, $tmparr);
		}
		//echo "\n".'<br/>Used Memory 1: '.memory_get_usage().' bytes';

		$xmlobj = new DomDocument;
		$xmlobj->loadXML($xml);
		$xslobj = new DomDocument;
		$xslobj->load($this->xsl_file);

		$xslt = new xsltprocessor;
		$xslt->importStyleSheet($xslobj);
		foreach($para as $key => $value) $xslt->setParameter('', $key, $value);
		//session_write_close(); //get2soap
		$result = $xslt->transformToXML($xmlobj);
		if(!$result) throw new plaf('The system has encountered an error that may be related to the data you are entering, please try again.', 2001);
		else echo $result;
		//echo "\n".'<br/>Used Memory 2: '.memory_get_usage().' bytes';
	}
}
?>

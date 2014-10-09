<?php
/*
PLAF:PLASER Front Fault => Exception class
*/

class plaf extends Exception
{
	public $error_xslfile = '/xsl/error/error.xsl';
	public $menu_xmlfile = '../general/menu.xml';
	public $detail;
	public $faultstring;
	public $faultactor;
	public $faultcode;
	public $addfili;
	public $file_line;

	public function __construct($detail=null, $faultstring=0, $faultactor=null, $faultcode='WEB_PLAF', $addfili=true)
	{
		$this->faultcode = $faultcode;
		$this->faultstring = $faultstring;
		$this->faultactor = $faultactor;
		$this->detail = $detail;

		$faultactor_conf = $GLOBALS['plaser_conf']->get('Error_return_level_faultactor');
		if($faultactor_conf)
		{
			$this->addfili = $addfili;
			$this->file_line = "{$this->file}#{$this->line}";
			if($this->addfili)
			{
				if($this->faultactor) $this->faultactor.= " [{$this->file_line}]";
				else $this->faultactor = "{$this->file_line}";
			}
		}
		elseif(!$this->faultactor) $this->faultactor = 'Web Server';

		parent::__construct();
	}

	//Show a Fault
	public function fault($xml=null)
	{
		//BEGIN Show debug from server
		if($this->faultstring == -92 || $this->faultstring == -93 || $this->faultstring == -94)
		{
			header('Content-type: text/xml');
			echo base64_decode($this->detail);
			exit;
		}
		//END Show debug

		$pf = new stdClass;
		$pf->faultstring = $this->faultstring;
		$pf->faultcode = $this->faultcode;
		$pf->faultactor = $this->faultactor;
		//$pf->detail = htmlentities($this->detail);
		$pf->detail = $this->detail; //I assume that all I got was a valid XML

		if(!$xml) //Return a html error page Fault
		{
			$xml = new PLASER_xmlFront();
			$xml->addx($pf, 'plaf', true);
			if(file_exists($this->menu_xmlfile))
				$xml->add('<menu_content>'.utf8_encode(file_get_contents($this->menu_xmlfile)).'</menu_content>');
			else $xml->add('<menu_content></menu_content>');
			//$XML = InsertInTAG("<splitter>".$_SESSION['splitter']."</splitter>", $XML, "root");
			chdir(dirname($_SERVER['DOCUMENT_ROOT'].$this->error_xslfile)) or die('Error 402, Directory error denied.');
			$xslt = new PLASER_xslt();
			$xslt->xsl_file = basename($this->error_xslfile);
			$xslt->process($xml->Create_XML());
		}
		else //Return a SOAP Fault
		{
			require_once 'PLASER/SoapFault.php';
			new PLASER_SoapFault($pf->faultcode, $pf->faultstring, $pf->faultactor, $pf->detail);
		}
	}

}//END class plaf
?>

<?php
/*
PLAF:PLASER Back Fault => Exception class
*/

class plaf extends Exception
{
	public $detail;
	public $faultstring;
	public $faultactor;
	public $faultcode;
	public $addfili;
	public $file_line;

	public function __construct($detail=null, $faultstring=0, $faultactor=null, $faultcode='SOAP-ENV:Server', $addfili=true)
	{
		if(!strstr($faultcode, 'SOAP-ENV:')) $this->faultcode = 'SOAP-ENV:'.$faultcode;
		else $this->faultcode = $faultcode;
		$this->faultstring = $faultstring;
		$this->faultactor = $faultactor;
		$this->detail = $detail;

		$faultactor_conf = $GLOBALS['plaser_conf']->get('Error_return_level_faultactor');
		if($faultactor_conf)
		{
			$this->addfili = $addfili;
			$this->file_line = "{$this->file}#{$this->line}";
			if(!$this->faultactor && isset($GLOBALS['plaser']) && ($GLOBALS['plaser']->get_target() != ''))
				$this->faultactor = $GLOBALS['plaser']->get_target();
			if($this->addfili)
			{
				if($this->faultactor) $this->faultactor.= " [{$this->file_line}]";
				else $this->faultactor = "{$this->file_line}";
			}
		}
		elseif(!$this->faultactor) $this->faultactor = 'CoreX Server';

		parent::__construct();
	}

	//Return a SOAP Fault
	public function fault()
	{
		require_once 'PLASER/SoapFault.php';
		new PLASER_SoapFault($this->faultcode, $this->faultstring, $this->faultactor, $this->detail);
	}

}//END class plaf
?>

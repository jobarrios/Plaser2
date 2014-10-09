<?php
/*****
Exception Class: Handle the Exceptions Not Controlled
*****/

class Exception_class
{
	private $code = 555;
	private $msg0 = 'Server Fatal Error.';
	private $exception;

	public function __construct($exception)
	{
		$this->exception = $exception;
	}

	public function get_arraymsg()
	{
		$e = & $this->exception;
		$msg = "Exception Not Controlled: \n";
		$msg.= "exception '".get_class($e)."' with message: '{$e->getMessage()}' \nin {$e->getFile()}:{$e->getLine()}";
		return array($this->msg0, $msg);
	}

	public function get_code() { return $this->code; }
}
?>

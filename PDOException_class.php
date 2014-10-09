<?php
/*****
PDOException Class
*****/

class PDOException_class
{
	private $code = 101;
	private $msg0 = 'Data Fatal Error.';
	private $PDO_libs = array('PDO_class.php', 'dbz_class.php');
	private $exception;

	public function __construct($exception)
	{
		$this->exception = $exception;
	}

	public function get_arraymsg()
	{
		$traces = $this->exception->getTrace();
		$i=0; while(in_array(basename($traces[$i]['file']), $this->PDO_libs)) $i++;
		$trace = $traces[$i];

		$msg  = $this->exception->getMessage();
		$msg  = str_ireplace(' check the manual that corresponds to your MySQL server version for the right syntax to use', '', $msg);
		$msg  = "Data Storage (PDO) Fatal Error: \n$msg \n";
		$msg .= "file: {$trace['file']} (on line: {$trace['line']}) \n";
		if( $trace['file'][0] == '/' && $filesrc = @file($trace['file']) )
			$msg .= "Code line in source file: \n" . trim($filesrc[$trace['line'] - 1]) . " \n";
		else
			$msg .= 'Calling: ' . @$trace['class'] . '->' . $trace['function'] . "(); \n";
		if($ct = count($trace['args']))
		{
			$msg .= "Actual argument: \n";
			if($ct == 1) $msg .= print_r($trace['args'][0], true);
			else $msg .= print_r($trace['args'], true);
		}
		return array($this->msg0, $msg);
	}

	public function get_code() { return $this->code; }
}
?>

<?php
/*****
DBz Class: Database Storage Abstract Layer former based on dbx
This is for backward compatibility with the older code,
you should use PDO and not this one.
*****/

require_once 'PLASER/PDO_class.php';
class DBz extends PLASER\PDO
{
	public $index = true;

	public function __construct() {parent::__construct();}

	public function query($q)
	{
		if($this->index) return $this->xsquery($q);
		else return $this->xsquery($q, true);
	}
}
?>

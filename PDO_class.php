<?php
/*****
PDO Class: Database Storage Abstract Layer
See the PDOException_class for the exception handler
*****/

namespace PLASER;
class PDO extends \PDO
{
	private $encoding = 'utf8';

	public function __construct($driver_options = null)
	{
		if( $GLOBALS['plaser_conf']->get('Corex') ) $storageID = $GLOBALS['plaser']->get_storageID();
		else $storageID = 'WEB'; //Web layer

		if(!$storageID)
		{
			$errmsg = array();
			$errmsg[1] = "Data Storage (PDO) Fatal Error: storageID not defined for the service {$GLOBALS['plaser']->get_service()} in {$GLOBALS['plaser_conf']->get('Services')}";
			throw new \plaf($errmsg, 100);
		}

		$mode = 'slave';
		if( $GLOBALS['plaser_conf']->get('Write_Methods_Corex')
				&& in_array(strtolower(substr($GLOBALS['plaser']->get_method(), -6, 6)), $GLOBALS['plaser_conf']->get('Write_Methods_Array'))
		) $mode = 'master';

		require $GLOBALS['plaser_conf']->get('DataStorage_conf');
		if(!($strg = $storage[$storageID][$mode]))
		{
			$errmsg = array();
			$errmsg[1] = "Data Storage (PDO) Fatal Error: \$storage['$storageID']['$mode'] not defined in {$GLOBALS['plaser_conf']->get('DataStorage_conf')}";
			throw new \plaf($errmsg, 100);
		}
		$dsn = $strg['dsn'];
		$user = $strg['user'];
		$passw = $strg['passw'];

		try { parent::__construct($dsn, $user, $passw, $driver_options); }
		catch (\PDOException $e)
		{
			$errmsg = array();
			$errmsg[0] = 'Data Fatal Error, please try again later.';
			$errmsg[1] = "Data Storage (PDO) Fatal Error: {$e->getMessage()} using DSN: $dsn";
			throw new \plaf($errmsg, 100);
		}
		$this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		if($this->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql')
		{
			//parent::query("SET CHARACTER SET '".$this->encoding."'"); //could go to the my.cnf config file
			//Put it in the /etc/my.cnf in section [mysqld] line: default-character-set=utf8
			parent::query("SET NAMES '".$this->encoding."'");
		}
	}

	public function squery($q, $assoc = null)
	{
		$res = parent::query($q);
		if($res->columnCount())
		{
			if($assoc) return $res->fetchAll(PDO::FETCH_ASSOC);
			else return $res->fetchAll();
		}
	}

	public function aquery($q)
	{
		return $this->squery($q, true);
	}

	public function xsquery($q, $assoc = null)
	{
		$res = new \stdClass;
		$res->rows = null;
		$res->data = $this->squery($q, $assoc);
		$res->rows = count($res->data);
		return $res;
	}

	public function xaquery($q)
	{
		return $this->xsquery($q, true);
	}
}
?>

<?php
/*****
DBz Class: Database Storage Abstract Layer, based on dbx
*****/

class DBz
{
	//Publics
	public $mode;
	public $resultmode;
	public $link;
	public $index = true;
	private $encoding = 'utf8';

	public function __construct( $mode=null, $result=DBX_RESULT_ASSOC, $module=null, $host=null, $db=null, $user=null, $passw=null )
	{
		if(!$mode && $GLOBALS['plaser_conf']->get('Write_Methods_Corex'))
		{
			$method = $GLOBALS['plaser']->get_method();
			if(in_array(strtolower(substr($method, -6, 6)), $GLOBALS['plaser_conf']->get('Write_Methods_Array'))) $mode = 'm';
			else $mode = 's';
		}
		if(!$mode) $mode = 'm';

		if($mode == 'master') $mode = 'm';
		elseif($mode == 'slave') $mode = 's';
		if(!($mode == 'm' || $mode == 's')) $this->dbzerror('Data Fatal Error, the DBz mode must be: [m],[s],[master],[slave].', 100);
		$set = $GLOBALS['plaser_conf']->get('DataStorage_Setting');
		require 'configdb.php';
		if($mode == 'm')
		{
			$module = $module?$module:$db_setting[$set]['dbz_master_module'];
			$host = $host?$host:$db_setting[$set]['dbz_master_host'];
			$db = $db?$db:$db_setting[$set]['dbz_master_db'];
			$user = $user?$user:$db_setting[$set]['dbz_master_user'];
			$passw = $passw?$passw:$db_setting[$set]['dbz_master_passw'];
			$this->mode = 'master';
		}
		else
		{
			$module = $module?$module:$db_setting[$set]['dbz_slave_module'];
			$host = $host?$host:$db_setting[$set]['dbz_slave_host'];
			$db = $db?$db:$db_setting[$set]['dbz_slave_db'];
			$user = $user?$user:$db_setting[$set]['dbz_slave_user'];
			$passw = $passw?$passw:$db_setting[$set]['dbz_slave_passw'];
			$this->mode = 'slave';
		}
		$this->resultmode = $result;

		$this->link = @dbx_connect($module, $host, $db, $user, $passw);
		if(!$this->link)
		{
			$str = "Data Fatal Error: Error connection to storage server. HOST: $host, DB: $db";
			if(!$GLOBALS['plaser_conf']->get('Development_Server'))
			{
				error_log("PLASER_DBz (101): $str");
				$str = 'Data Fatal Error, please try again later.';
			}
			$this->dbzerror($str, 101);
		}
		dbx_query($this->link, "SET CHARACTER SET '".$this->encoding."'"); //could go to the my.cnf config file
		dbx_query($this->link, "SET NAMES '".$this->encoding."'");
		return $this->link;
	}

	public function query($q, $r=null)
	{
		if($r) $this->resultmode = $r;
		$res = dbx_query($this->link, $q, $this->resultmode);
		if(!$res)
		{
			$str = dbx_error($this->link);
			$str = str_replace('.  Check the manual that corresponds to your MySQL server version for the right syntax to use', '', $str);
			$str = 'Data Fatal Error: ' . $str;
			$str.= "\n".'The query was: ' . $q;
			if(!$GLOBALS['plaser_conf']->get('Development_Server'))
			{
				error_log("PLASER_DBz (102): $str");
				$str = 'Data Fatal Error.';
			}
			$this->dbzerror($str, 102);
		}

		if(!$this->index)
		{
			$arr1 = array();
			foreach($res->data as $rows)
			{
				$arr2 = array();
				foreach($rows as $key => $value) if(!is_int($key)) $arr2[$key] = $value;
				$arr1[] = $arr2;
			}
			$res->data = array();
			$res->data = $arr1;
		}
		return $res;
	}

	public function close()
	{
		dbx_close($this->link);
		$this->link = null;
	}

	private function dbzerror($str, $code)
	{
		throw new plaf($str, $code);
	}

}
?>

<?php
/*****
PLASER exceptions handler
*****/

$realplaf = $exception instanceof plaf;
if($realplaf) $pf = &$exception;
else //Convert the exception into a plaf exception
{
	$fclass = 'PLASER/'.get_class($exception).'_class.php';
	if(include $fclass)
	{
		$eclass = get_class($exception).'_class';
		$e = new $eclass($exception);
	}
	else //Not controlled exception neither Exception Obj
	{
		include 'PLASER/Exception_class.php';
		$e = new Exception_class($exception);
	}
	$pf = new plaf($e->get_arraymsg(), $e->get_code()); //this $pf is plaf but not a realplaf
}

$corex = $GLOBALS['plaser_conf']->get('Corex');

//The negative 'faultstring' are considered operational messages so they are no logs neither change level_detail
if(intval($pf->faultstring) > -1)
{
	$detail = array();
	$detail[-1] = 'System Error.'; //Note that even with this setting the negative errors are always shown
	if(is_array($pf->detail))
	{
		$detail[0] = trim(@$pf->detail[0]);
		$detail[1] = trim(@$pf->detail[1]);
		if(!$detail[0]) $detail[0] = 'Server Fatal Error.';
		if(!$detail[1]) $detail[1] = $detail[0];
	}
	else $detail[0] = $detail[1] = $pf->detail;
	$detail[2] = $exception->__toString();
	$detail[3] = print_r($exception, true);
	$signature = $corex?'PLAF_CoreX':'PLAF_Web';

	//BEGIN: Errorlog
	$errorlog_level = $GLOBALS['plaser_conf']->get('Error_errorlog_level_detail');
	if($errorlog_level == 0 || ($errorlog_level == 1 && $realplaf))
		error_log("{$signature}: STR:({$pf->faultstring}) DET:({$detail[$errorlog_level]}) SRC:({$pf->faultcode} {$pf->faultactor})");
	else error_log("{$signature}: " . $detail[$errorlog_level]);
	//END: Errorlog

	//BEGIN: Plaserlog
	$plaserlog_level = $GLOBALS['plaser_conf']->get('Error_plaserlog_level_detail');
	try
	{
		if(isset($GLOBALS['plaser']))
		{
			if($corex)
			{
				/*
				$methods_nologs = array('rtd_chk','ltd_chk');
				if (!in_array($GLOBALS['plaser']->get_method(), $methods_nologs))
					$GLOBALS['plaser']->log($detail[$plaserlog_level], $pf->faultstring, null, null, null, "{$pf->faultcode} {$pf->faultactor}", null, null, true);
				*/
				$methods_nologs = array('user_rights','ltd_chk');
				$codes_nologs = array(503, 506, 507, 508);
				if (!( in_array($GLOBALS['plaser']->get_method(), $methods_nologs) || in_array($pf->faultstring, $codes_nologs) ))
					$GLOBALS['plaser']->log($detail[$plaserlog_level], $pf->faultstring, null, null, null, "{$pf->faultcode} {$pf->faultactor}", null, null, true);
			}
			else $GLOBALS['plaser']->log($detail[$plaserlog_level], $pf->faultstring, null, null, null, "{$pf->faultcode} {$pf->faultactor}");
		}
	}
	catch (plaf $pf)
	{
		error_log("{$signature}(2): STR:({$pf->faultstring}) DET:({$pf->detail}) SRC:({$pf->faultcode} {$pf->faultactor})");
	}
	//END: Plaserlog

	//BEGIN: Return (display)
	$return_level = $GLOBALS['plaser_conf']->get('Error_return_level_detail');
	$pf->detail = $detail[$return_level];
	//END: Return
}
elseif(is_array($pf->detail)) $pf->detail = @$pf->detail[0];

if(!$corex && isset($GLOBALS['plaser']) && $GLOBALS['plaser']->error_returnxml) $pf->fault(1);
else $pf->fault();
exit;
?>

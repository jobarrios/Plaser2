<?php
/*****
Initial process of the Web
*****/

function debug($var = null, $append = null)
{
	include_once 'Util/debug_dump.php';
	debug_dump($var, $append);
}

if(strlen($_SERVER['PHP_SELF']) > 200) exit('Error 1900');
if($_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF'] == $_SERVER['SCRIPT_FILENAME']) { header('Location: /'); exit; }
//BEGIN General Configuration
require 'PLASER_CONF/plaser_conf.php';
$plaser_conf = new PLASER_Conf_class(0);
//END General Configuration

$PLASER_OneEntry = @$PLASER_OneEntry;

if($PLASER_OneEntry && !file_exists($_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF']))
{
	header('Not Found', true, 404);
	error_log("PLASER_Web: [Client {$_SERVER['REMOTE_ADDR']}] File Not Found(404): {$_SERVER['DOCUMENT_ROOT']}{$_SERVER['PHP_SELF']}");
	$resp = '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">';
	$resp.= '<HTML><HEAD><TITLE>404 Not Found</TITLE></HEAD><BODY><H1>Not Found</H1>';
	$resp.= 'The requested URL '.$_SERVER['SCRIPT_NAME'].' was not found on this server by PLASER.<P>';
	$resp.= '<HR>'.$_SERVER['SERVER_SIGNATURE'].'</BODY></HTML>';
	echo $resp;
	exit;
}

require 'PLASER/plafFront_class.php';
function exception_handler_plaser($exception) { require 'PLASER/exception_handler.php'; }
set_exception_handler('exception_handler_plaser');

//MAIN Process
if($PLASER_OneEntry)
{
	chdir(dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF'])) or die('Error 401, Directory Access Denied.');
}

require 'PLASER/ClientFront.php';
require 'PLASER/Web.php';
$plaser = new PLASER_Web();
require 'PLASER/xmlFront.php';
require 'PLASER/xslt.php';
$plaser->_chk_permit();

if($PLASER_OneEntry)
{
	include_once(basename($_SERVER['PHP_SELF']));
	exit;
}
?>

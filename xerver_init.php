<?php
/*****
PLASER_Server_init
*****/

chdir(dirname($_SERVER['DOCUMENT_ROOT'].$_SERVER['PHP_SELF']));
/*
if(pathinfo($_SERVER['PHP_SELF'], PATHINFO_EXTENSION) == 'php')
{
	if(isset($_GET['wsdl']))
	{
		$service = pathinfo($_SERVER['PHP_SELF'], PATHINFO_FILENAME);
		if(file_exists($service . '.xml'))
		{
			include 'WSDL_generate.php';
			$wsdl = new PLASER_WSDL_Generate($service);
			echo $wsdl->generate();
			exit;
		}
	}
	else { include_once(basename($_SERVER['PHP_SELF'])); }
}
else readfile(basename($_SERVER['PHP_SELF']));
*/
?>

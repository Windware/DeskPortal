<?php
	require_once('../server/init.php'); #Load system library

	#Set the requesting file manually
	$_GET['_version'] = System_Static::app_conf('system', 'static', 'system_version');
	$_GET['_self'] = 'system/'.str_replace('_', '/', System_Static::app_conf('system', 'static', 'system_version')).'/server/php/start.php';

	System_Static::file_load('router-php.'.System_Static::app_conf('system', 'static', 'ext_php')); #Pass processing to the router script
?>

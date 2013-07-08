<?php
	switch($_GET['type'])
	{
		case 'ico' : $icon = 'icon.ico'; break;

		case 'png' : $icon = 'icon.png'; break;

		case 'apple' : $icon = 'icon-apple.png' ;break;

		default : exit; break;
	}

	require_once '../server/init.php'; //Load system library

	//Set the requesting file manually
	$_GET['_version'] = System_Static::app_conf('system', 'static', 'system_version');
	$_GET['_self'] = 'system/'.str_replace('_', '/', System_Static::app_conf('system', 'static', 'system_version'))."/client/default/common/image/$icon";

	unset($icon);
	System_Static::file_load('router-php.'.System_Static::app_conf('system', 'static', 'ext_php')); //Pass processing to the router script

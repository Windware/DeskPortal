<?php
$system = new System_1_0_0(__FILE__);
$system->cache_header(0); //Do not cache

switch($_GET['task'])
{
	case 'login.process' : //Log the user in and return the login information
		$result = Login_1_0_0::process($_GET['identity'], $_GET['pass'], $_GET['keep']);
		print $system->xml_send($result['status'], $result['data']);
	break;
}

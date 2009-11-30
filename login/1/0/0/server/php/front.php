<?php
	$system = new System_1_0_0(__FILE__);

	switch($_GET['task'])
	{
		case 'login.process' : #Log the user in and return the login information
			$system->cache_header(0); #Do not cache

			$result = Login_1_0_0::process($_POST['user'], $_POST['pass'], $_POST['keep']);
			print $system->xml_send($result['status'], $result['data']);
		break;
	}
?>

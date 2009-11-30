<?php
	$system = new System_1_0_0(__FILE__);

	switch($_GET['task'])
	{
		case 'gui.expand' :
			$system->cache_header(0); #Do not cache
			print $system->xml_send(Launcher_1_0_0::expand($_POST['category'], $_POST['state']));
		break;

		case 'gui.list' :
			$system->cache_header(0); #Do not cache

			$data = Launcher_1_0_0::apps($_GET['language']);
			print $system->xml_send($data !== false, $data);
		break;
	}
?>

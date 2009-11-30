<?php
	$system = new System_1_0_0(__FILE__);

	switch($_GET['task'])
	{
		case 'gui.fetch' : #Get the announcement for a month
			$system->cache_header(0); #Do not cache in case news are added for that month

			$data = Announce_1_0_0_Item::get($_GET['year'], $_GET['month'], $_GET['language']);
			print $system->xml_send($data !== false, $data);
		break;
	}
?>

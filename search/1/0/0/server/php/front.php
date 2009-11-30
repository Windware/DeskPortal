<?php
	$system = new System_1_0_0(__FILE__);

	switch($_GET['task'])
	{
		case 'gui.page' : case 'gui.search' : #Search for phrases
			$system->cache_header(0); #Do not cache

			$result = Search_1_0_0_Item::search($_GET['search'], $_GET['area'], $_GET['page']);
			print $system->xml_send($result !== false, $result);
		break;
	}
?>

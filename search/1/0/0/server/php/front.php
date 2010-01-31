<?php
	$system = new System_1_0_0(__FILE__);

	switch($_GET['task'])
	{
		case 'gui.page' : case 'gui.search' : #Search for phrases
			$xml = '';
			$system->cache_header(30); #Cache for same searches within a short period

			$data = Search_1_0_0_Item::search($_GET['search'], $_GET['area'], $_GET['page']);

			if(is_array($data))
			{
				foreach($data as $row)
				{
					$list = '';

					if(is_array($row['child'])) foreach($row['child'] as $child) $list .= $system->xml_node($child['name'], $child['attributes']);
					$xml .= $system->xml_node('app', $row['attributes'], $list);
				}
			}

			print $system->xml_send($data !== false, $xml);
		break;
	}
?>

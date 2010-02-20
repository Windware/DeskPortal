<?php
	$system = new System_1_0_0(__FILE__);
	$system->cache_header(0); #Do not cache

	switch($_GET['task'])
	{
		case 'conf.save' :
			$user = $system->user();
			print $system->xml_dump($user->save('conf', $_POST, $system->self['id']));
		break;

		case 'gui.expand' :
			$result = Launcher_1_0_0_Item::expand($_POST['category'], $_POST['state']);
			print $system->xml_dump($result);
		break;

		case 'gui.list' :
			$data = Launcher_1_0_0_Item::get($_GET['language']);
			$xml = '';

			if(is_array($data))
			{
				foreach($data as $category) #For all categories
				{
					$list = '';
					foreach($category['entry'] as $entry) $list .= $system->xml_node('entry', $entry); #Add each app names

					$xml .= $system->xml_node('category', $category['attributes'], $list);
				}
			}

			print $system->xml_send($data !== false, $xml);
		break;
	}
?>

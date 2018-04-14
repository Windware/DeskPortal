<?php
$system = new System_1_0_0(__FILE__);
$system->cache_header(0); #Do not cache

switch($_GET['task'])
{
	case 'conf.save' :
		$user = $system->user();

		$state = $user->save('conf', ['display' => $_POST['display']], $system->self['id']);
		$exclude = Launcher_1_0_0_Item::exclude($_POST['exclude'], 1, $user);

		print $system->xml_dump($state && $exclude);
	break;

	case 'gui.expand' :
		$result = Launcher_1_0_0_Item::expand($_POST['category'], $_POST['state']);
		print $system->xml_dump($result);
	break;

	case 'run' :
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

<?php
	$system = new System_1_0_0(__FILE__);
	$system->cache_header(0); #Do not cache

	switch($_GET['task'])
	{
		case 'group.get' : #Get groups
			$data = Addressbook_1_0_0_Group::get();
			print $system->xml_dump($data !== false, 'group', $data);
		break;

		case 'group.remove' : #Remove a group
			$result = Addressbook_1_0_0_Group::remove($_POST['group']);
			$data = Addressbook_1_0_0_Group::get();

			print $system->xml_dump($result && $data !== false, 'group', $data);
		break;

		case 'group.set' : #Set a group information
			$result = Addressbook_1_0_0_Group::set($_POST['name'], $_POST['id']);
			$data = Addressbook_1_0_0_Group::get();

			print $system->xml_dump($result && $data !== false, 'group', $data);
		break;

		case 'item.get' : #Get list of entries
			$data = Addressbook_1_0_0_Item::get($_GET['group']);
			print $system->xml_dump($data !== false, 'address', $data, array('user'));
		break;

		case 'item.remove' : #Remove an entry
			$result = Addressbook_1_0_0_Item::remove($_POST['id']);
			print $system->xml_dump($result);
		break;

		case 'item.set' : #Save content
			$result = Addressbook_1_0_0_Item::set($_POST['id'], $_POST);
			print $system->xml_dump($result);
		break;
	}
?>

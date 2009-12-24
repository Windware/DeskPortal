<?php
	$system = new System_1_0_0(__FILE__);
	$system->cache_header(0); #Do not cache

	switch($_GET['task'])
	{
		case 'conf.set' : #Set a group information
			$result = Memo_1_0_0_Group::set($_POST['name'], $_POST['id']);
			$data = Memo_1_0_0_Group::get();

			print $system->xml_send($result && $data !== false, $data);
		break;

		case 'group.get' : #Get groups
			$data = Memo_1_0_0_Group::get();
			print $system->xml_send($data !== false, $data);
		break;

		case 'group.remove' : #Remove a group
			$result = Memo_1_0_0_Group::remove($_POST['group']);
			$data = Memo_1_0_0_Group::get();

			print $system->xml_send($result && $data !== false, $data);
		break;

		case 'item.get' : #Get list of memo
			$data = Memo_1_0_0_Item::get();
			print $system->xml_send($data !== false, $data);
		break;

		case 'item.remove' : #Remove a memo
			$result = Memo_1_0_0_Item::remove($_POST['id']);
			$data = Memo_1_0_0_Item::get();

			print $system->xml_send($result && $data !== false, $data);
		break;

		case 'item.save' : #Save a memo
			$result = Memo_1_0_0_Item::save($_POST['id'], $_POST['content']);
			print $system->xml_send($result);
		break;

		case 'item.set' : #Set a memo information
			$result = Memo_1_0_0_Item::set($_POST['name'], $_POST['groups'], $_POST['id'] ? $_POST['id'] : 0);
			$data = Memo_1_0_0_Item::get();

			print $system->xml_send($result && $data !== false, $data);
		break;

		case 'item.show' : #Get a memo's content
			$data = Memo_1_0_0_Item::show($_GET['id']);
			print $system->xml_send($data !== false, $data, null, true);
		break;
	}
?>

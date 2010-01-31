<?php
	$system = new System_1_0_0(__FILE__);
	$system->cache_header(0); #Do not cache

	switch($_GET['task'])
	{
		case 'group.get' : #Get the list of categories
			$data = Calendar_1_0_0_Group::get();
			print $system->xml_dump($data !== false, 'category', $data, array('user'));
		break;

		case 'group.remove' : #Remove a category
			$result = Calendar_1_0_0_Group::remove($_POST['category']);
			$data = Calendar_1_0_0_Group::get();

			print $system->xml_dump($result && $data !== false, 'category', $data, array('user'));
		break;

		case 'group.set' : #Submit the category changes
			$result = Calendar_1_0_0_Group::set($_POST['id'], $_POST['name'], $_POST['color']);
			$data = Calendar_1_0_0_Group::get();

			print $system->xml_dump($result && $data !== false, 'category', $data, array('user'));
		break;

		case 'item.get' : #Get the schedule listing
			$data = Calendar_1_0_0_Item::get($_GET['year'], $_GET['month']);

			if(is_array($data)) foreach($data as $row) $xml .= $system->xml_node('schedule', $row, $system->xml_data($row['content']), array('user', 'content'));
			print $system->xml_send($data !== false, $xml);
		break;

		case 'item.remove' : #Remove a schedule
			$result = Calendar_1_0_0_Item::remove($_POST['year'], $_POST['month'], $_POST['day']);
			$data = Calendar_1_0_0_Item::get($_POST['year'], $_POST['month']);

			if(is_array($data)) foreach($data as $row) $xml .= $system->xml_node('schedule', $row, $system->xml_data($row['content']), array('user', 'content'));
			print $system->xml_send($result && $data !== false, $xml);
		break;

		case 'item.set' : #Edit the schedule
			$result = Calendar_1_0_0_Item::set($_POST['day'], $_POST['title'], $_POST['content'], $_POST['category'], $_POST['start'], $_POST['end']);
			print $system->xml_dump($result);
		break;
	}
?>

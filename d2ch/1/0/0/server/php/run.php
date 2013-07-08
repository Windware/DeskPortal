<?php
	$system = new System_1_0_0(__FILE__);
	$system->cache_header(0); #Do not cache any of the results

	switch($_GET['task'])
	{
		case 'message.get' : #Get messages for a thread
			$list = D2ch_1_0_0_Message::get($_GET['thread']);
			if(!is_array($list)) return $system->xml_dump(false);

			foreach($list as $row)
			{
				$message = $row['message'];
				unset($row['message']);

				array_walk($row, 'htmlspecialchars');
				$xml .= $system->xml_node('message', $row, $system->xml_data($message), array('thread'));
			}

			print $system->xml_send(true, $xml);
		break;

		case 'run' : #Get board list
			$board = D2ch_1_0_0_Board::get();
			$category = D2ch_1_0_0_Category::get();

			if($board === false || $category === false)
			{
				print $system->xml_dump(false);
				return;
			}

			$list = array();
			foreach($board as $info) $list[$info['category']][] = $info; #Categorize the boards

			foreach($category as $id => $name)
			{
				$include = ''; #List of boards included in the category
				foreach($list[$id] as $entry) $include .= $system->xml_node('board', array('id' => $entry['id'], 'name' => $entry['name']));

				$xml .= $system->xml_node('category', array('id' => $id, 'name' => $name), $include);
			}

			print $system->xml_send(true, $xml);
		break;

		case 'thread.get' : #Get thread list
			$list = D2ch_1_0_0_Thread::get($_GET['board']);
			print $system->xml_dump($list !== false, 'thread', $list, array('board', 'file', 'number', 'updated'));
		break;
	}
?>

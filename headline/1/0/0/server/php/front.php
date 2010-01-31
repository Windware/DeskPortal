<?php
	$system = new System_1_0_0(__FILE__);
	$system->cache_header(0); #Do not cache

	switch($_GET['task'])
	{
		case 'category.get' : #List categories
			$data = Headline_1_0_0_Category::get();
			print $system->xml_dump($data !== false, 'category', $data);
		break;

		case 'category.remove' : #Remove a category
			$result = Headline_1_0_0_Category::remove($_POST['category']);
			$data = Headline_1_0_0_Category::get();

			print $system->xml_dump($result && $data !== false, 'category', $data);
		break;

		case 'category.set' : #Set a category
			$result = Headline_1_0_0_Category::set($_POST['name'], $_POST['id']);
			$data = Headline_1_0_0_Category::get();

			print $system->xml_dump($result && $data !== false, 'category', $data);
		break;

		case 'entry.category' : #Set a category for an entry
			$result = Headline_1_0_0_Entry::set(false, $_POST['id'], 'category', $_POST['category']);
			print $system->xml_dump($result);
		break;

		case 'entry.get' : #Get the content of a feed entry (Entries are updated in the periodic schedule subsystem)
			$data = Headline_1_0_0_Entry::get($_GET);

			foreach($data['entry'] as $row) $xml .= $system->xml_node('entry', $row['attributes'], $system->xml_data($row['data']));
			$xml .= $system->xml_node('amount', array('value' => $data['amount']));

			print $system->xml_send($data !== false, $xml, null, true);
		break;

		case 'entry.mark' : #Mark an entry as the specified mode
			$result = Headline_1_0_0_Entry::set(false, $_POST['id'], 'rate', $_POST['mode']);
			print $system->xml_dump($result);
		break;

		case 'entry.show' : #Show the entry's display information from its ID
			$data = Headline_1_0_0_Entry::show($_GET['id']);
			print $system->xml_dump($data !== false, 'feed', array($data));
		break;

		case 'feed.add' : #Register a new rss : FIXME - Avoid letting an user register a same feed with different query parameters to DoS on it (ex : 10 feed per url space [without the query parameters])
			$feed = new Headline_1_0_0_Feed($_POST['address']); #Register to be fetched on the server side (This may take some time for a new feed fetching it)
			if($feed->id) $result = Headline_1_0_0_Feed::subscribe($feed->id); #Subscribe the feed for the user

			$data = Headline_1_0_0_Feed::get();
			print $system->xml_dump($result === true && $data !== false, 'feed', $data);
		break;

		case 'feed.get' : #Get the list of feeds
			$data = Headline_1_0_0_Feed::get();
			print $system->xml_dump($data !== false, 'feed', $data);
		break;

		case 'feed.remove' : #Unsubscribe a user from a feed
			$result = Headline_1_0_0_Feed::unsubscribe($_POST['id']);
			$data = Headline_1_0_0_Feed::get();

			print $system->xml_dump($result === true && $data !== false, 'feed', $data);
		break;

		case 'feed.sample' : #Insert sample feeds into user's database
			$result = Headline_1_0_0_Feed::sample($_POST['language']);
			$data = Headline_1_0_0_Feed::get();

			print $system->xml_dump($result === true && $data !== false, 'feed', $data);
		break;

		case 'gui.period' : #Alter the display period settings for a feed
			$result = Headline_1_0_0_Entry::set(true, $_POST['id'], 'period', $_POST['mode']);
			print $system->xml_dump($result);
		break;

		case 'gui.view' : #Mark the entry as read
			$result = Headline_1_0_0_Entry::set(false, $_POST['id'], 'read', $_POST['mode']);
			print $system->xml_dump($result);
		break;
	}
?>

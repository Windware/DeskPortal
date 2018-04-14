<?php
$system = new System_1_0_0(__FILE__);
$system->cache_header(0); #Do not cache any of the results

switch($_GET['task'])
{
	case 'cache.add' : #Create a cache for a bookmark and return the cache creation time
		$result = Bookmark_1_0_0_Cache::add($_POST['id']); #Return value : 0 (Success), 1 (Error), 2 (Target too big)

		if($result === 0) $data = $system->xml_node('cache', ['time' => $system->date_datetime()]);
		if($data === false) $result = 1;

		print $system->xml_send($result, $data);
	break;

	case 'conf._2_import' : #Loads bookmarks uploaded into the database
		$result = Bookmark_1_0_0_Item::import($_FILES['bookmark']['tmp_name']);
		print $result ? 0 : 1;
	break;

	case 'gui.cache' : #Get the cache content in HTML
		$result = Bookmark_1_0_0_Cache::get($_GET['id']);

		if(is_array($result))
		{
			$system->compress_header(); #Send compressed headers since the cache is stored compressed

			header('Content-Length: ' . strlen($result['content'])); #Give its length
			if(preg_match('|^[\-\w]+/[\-\w]+$|', $result['type'])) header("Content-Type: {$result['type']}"); #Give its mime type

			if($result['type'] != 'text/html' && $name = basename($result['file']))
				header("Content-Disposition: inline; filename=$name"); #Pass the same file name for non HTML files

			print $result['content'];
		}
	break;

	case 'item.add' : #Add a new bookmark
		$result = Bookmark_1_0_0_Item::add($_POST['address']);
		print $system->xml_dump($result === null ? 2 : !!$result);
	break;

	case 'item.get' : #Get the list of bookmarks
		$data = Bookmark_1_0_0_Item::get(is_array($_GET['cat']) ? $_GET['cat'] : []);
		print $system->xml_dump($data !== false, 'bookmark', $data, null, true);
	break;

	case 'item.remove' : #Remove a bookmark
		$result = Bookmark_1_0_0_Item::remove($_POST['id']);
		print $system->xml_dump($result);
	break;

	case 'item.set' : #Sets a bookmark's information
		$result = Bookmark_1_0_0_Item::set($_POST['address'], $_POST['name'], $_POST['cat'], $_POST['id']);
		print $system->xml_dump($result);
	break;

	case 'item.viewed' : #Increase the view count of a page
		$result = Bookmark_1_0_0_Item::viewed($_POST['address']);
		print $system->xml_dump($result);
	break;

	case 'group.get' : #Get the list of categories
		$data = Bookmark_1_0_0_Group::get();
		print $system->xml_dump($data !== false, 'category', $data);
	break;

	case 'group.remove' : #Remove a category
		$result = Bookmark_1_0_0_Group::remove($_POST['group']);
		$data = Bookmark_1_0_0_Group::get();

		print $system->xml_dump($result && $data !== false, 'category', $data);
	break;

	case 'group.set' : #Create a new category
		$result = Bookmark_1_0_0_Group::set($_POST['name'], $_POST['id']);
		$data = Bookmark_1_0_0_Group::get();

		print $system->xml_dump($result && $data !== false, 'category', $data);
	break;
}
?>

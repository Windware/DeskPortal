<?php
	$system = new System_1_0_0(__FILE__);

	switch($_GET['task'])
	{
		case 'gui.apply' : #Apply the configuration : #FIXME - Arbitary number of $_POST can inflate the database size
			$system->cache_header(0); #Do not cache

			$user = $system->user();
			print $system->xml_send($user->save('conf', $_POST, 'system_static'));
		break;

		case 'background.get' : #Get list of available wallpapers
			$system->cache_header(0); #Do not cache

			$data = Setting_1_0_0_Background::get();
			print $system->xml_send($data !== false, $data);
		break;

		case 'background.thumbnail' : #Get the wallpaper as a thumbnail
			$system->cache_header(); #Cache the result

			$image = $system->image_thumbnail($_GET['file'], true);
			if($image) print $image;
		break;
	}
?>

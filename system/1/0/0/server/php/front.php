<?php
	$system = new System_1_0_0(__FILE__);

	switch($_GET['task'])
	{
		case 'app.conf' : #Save user configuration
			$system->cache_header(0); #Do not cache
			$user = $system->user();

			$status = $user->valid ? $user->save('conf', $_POST, $_GET['id']) : -1;
			print $system->xml_send($status);
		break;

		case 'conf.apply' : #Apply general configuration tab changes
			$system->cache_header(0); #Do not cache
			$user = $system->user();

			if(!$user->valid) print $system->xml_send(-1);
			else
			{
				$result = $user->save('version', array('name' => $_POST['name'], 'version' => $_POST['version']));
				print $system->xml_send($result !== false, $result);
			}
		break;

		case 'conf.swap' : #Get version listing
			$system->cache_header(0); #Do not cache

			$data = $system->app_available($_GET['app']);
			print $system->xml_send($data !== false, $data);
		break;

		case 'image.wallpaper' : #Save user wallpaper
			$system->cache_header(0); #Do not cache
			$user = $system->user();

			if(!$user->valid) print $system->xml_send(-1);
			else print $system->xml_send($user->save('conf', array('wallpaper' => $_POST['name']), 'system_static'));
		break;

		case 'motion.init' : case 'window.create' : #Create window background image
			header('Content-Type: image/png'); #Set the content type as an image

			$result = $system->image_background($_GET); #Get the image data
			if($result === false) return;

			header('Content-Length: '.strlen($result)); #Send the file size
			$system->cache_header(); #Send cache headers

			print $result; #Send the image
		break;

		case 'network.fetch' : #Get a list of files in XML package
			$compressed = $system->compress_possible(); #Check for compress possibility

			$result = $system->file_package($_GET['file'], $compressed); #Get the XML file list
			if($result === false) $result = $system->xml_format(''); #Try to send an empty list if failed

			$system->cache_header(); #Send cache headers
			if($compressed) $system->compress_header(); #Send gzip header if possible

			$system->xml_header(); #Send XML header
			header('Content-Length: '.strlen($result)); #Print out the length header

			print $result; #Send the list
		break;

		case 'tool.create' : case 'tool.fade' : case 'window.save' : #Save app states
			$system->cache_header(0); #Do not cache
			$user = $system->user();

			if(!$user->valid) print $system->xml_send(-1);
			else
			{
				$data = $_POST;
				unset($data['id']);

				$result = $user->save($_GET['section'], $data, $_POST['id']);
				print $system->xml_send($result !== false, $result);
			}
		break;

		case 'user.conf' : #Get user app confs
			$system->cache_header(0); #Do not cache
			$user = $system->user();

			if(!$user->valid) print $system->xml_send(-1);
			else
			{
				$list = is_array($_GET['app']) ? $_GET['app'] : array();
				$result = $user->xml('conf', $list).$user->xml('window', $list);

				print $system->xml_send($result !== false, $result);
			}
		break;

		case 'user.info' : #Get list of apps available
			$system->cache_header(0); #Do not cache
			$user = $system->user();

			if(!$user->valid) print $system->xml_send(-1);
			else
			{
				$result = $user->xml('used', null, $_GET['language']); #Get user pref list
				print $system->xml_send($result !== false, $result);
			}
		break;

		case 'user.refresh' : #Refresh the user ticket expire time
			$system->cache_header(0); #Do not cache

			$user = $system->user();
			print $system->xml_send($user->valid ? $user->refresh() : -1);
		break;
	}
?>

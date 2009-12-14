<?php
	$system = new System_1_0_0(__FILE__);
	$system->cache_header(0); #Do not cache

	switch($_GET['task'])
	{
		case 'conf.account' : #Save account information
			$option = $_POST;
			unset($option['account']);

			print $system->xml_send(Mail_1_0_0_Account::save($_POST['account'], $option));
		break;

		case 'account.get' : #Get list of accounts
			$data = Mail_1_0_0_Account::get();
			print $system->xml_send($data !== false, $data);
		break;

		case 'folder.get' : #Get list of folders for an account
			$data = Mail_1_0_0_Folder::get($_GET['account']);
			print $system->xml_send($data !== false, $data);
		break;

		case 'item.get' : #Get list of mails
			$system->cache_header(0); #Avoid caching server response (Cache on client side instead)

			$data = Mail_1_0_0_Item::get($_GET['account'], $_GET['folder']);
			print $system->xml_send($data !== false, $data, null, true);
		break;

		case 'item.show' : #Get message body of a mail
			#NOTE : Cache on client side instead in case server does not support unique ID and the content may change upon requesting for same ID using sequential numbers
			$system->cache_header(0);

			$data = Mail_1_0_0_Item::show($_GET['account'], $_GET['folder'], $_GET['message']);
			print $system->xml_send($data !== false, $data, null, true);
		break;
	}
?>

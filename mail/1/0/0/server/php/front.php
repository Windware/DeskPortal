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

		case 'item.get' : #Get list of mails
			$system->cache_header(0); #Avoid caching server response (Cache on client side instead)

			$data = Mail_1_0_0_Item::get($_GET['account'], $_GET['folder']);
			print $system->xml_send($data !== false, $data, null, true);
		break;
	}
?>

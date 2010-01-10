<?php
	$system = new System_1_0_0(__FILE__);
	$system->cache_header(0); #Avoid caching server response (Cache on client side instead)

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
			$update = $_GET['update'] ? Mail_1_0_0_Folder::update($_GET['account']) : true;
			$data = Mail_1_0_0_Folder::get($_GET['account']);

			print $system->xml_send($update !== false && $data !== false, $data);
		break;

		case 'item.get' : #Get list of mails stored in the database
			if($_GET['update']) $update = Mail_1_0_0_Item::update($_GET['folder']); #Update it from the mail server
			$data = Mail_1_0_0_Item::get($_GET['folder'], $_GET['page'], $_GET['order'], $_GET['reverse'], $_GET['marked'], $_GET['unread'], $_GET['search']); #Get list from database

			print $system->xml_send($update !== false && $data !== false, $data, null, true);
		break;

		case 'item.mark' : #Mark mails
			print $system->xml_send(Mail_1_0_0_Item::flag($_POST['id'], 'Flagged', !!$_POST['mode']));
		break;

		case 'item.move' : #Move mails
			print $system->xml_send(Mail_1_0_0_Item::move($_POST['id'], $_POST['folder']));
		break;

		case 'item.trash' : #Move mails to trash
			print $system->xml_send(Mail_1_0_0_Item::special($_POST['id'], 'trash', $_POST['account']));
		break;

		case 'item.show' : #Get message body of a mail and return as is
			$system->cache_header(); #Cache mail body
			print Mail_1_0_0_Item::show($_GET['message']);
		break;

		case 'item.trash' : #Trash mails
			print $system->xml_send(Mail_1_0_0_Item::trash($_POST['id']));
		break;
	}
?>

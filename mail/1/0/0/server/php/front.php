<?php
	$system = new System_1_0_0(__FILE__);
	$system->cache_header(0); #Avoid caching server response (Cache on client side instead)

	switch($_GET['task'])
	{
		case 'account.get' : #Get list of accounts
			$data = Mail_1_0_0_Account::get();
			print $system->xml_send($data !== false, $data);
		break;

		case 'conf.create' : #Create a folder
			$result = Mail_1_0_0_Folder::create($_POST['account'], $_POST['parent'], $_POST['name']);
			$data = Mail_1_0_0_Folder::get($_POST['account'], false);

			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'conf.folder' : case 'folder.get' : #Get list of folders for an account
			$result = $_GET['update'] ? Mail_1_0_0_Folder::update($_GET['account']) : true;
			$data = Mail_1_0_0_Folder::get($_GET['account'], $_GET['subscribed']);

			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'conf.move' : #Move a folder
			$result = Mail_1_0_0_Folder::move($_POST['folder'], $_POST['target']);
			$data = Mail_1_0_0_Folder::get(Mail_1_0_0_Folder::account($_POST['folder']), false);

			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'conf.rename' : #Rename a folder
			$result = Mail_1_0_0_Folder::rename($_POST['folder'], $_POST['name']);
			$data = Mail_1_0_0_Folder::get(Mail_1_0_0_Folder::account($_POST['folder']), false);

			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'conf.remove' : #Delete folders
			$account = Mail_1_0_0_Folder::account($_POST['folder'][0]);
			$result = Mail_1_0_0_Folder::remove($_POST['folder'], true);

			$data = Mail_1_0_0_Folder::get($account, false);
			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'conf.special' : #Set special folders
			$folder = $_POST;
			unset($folder['account']);

			$result = Mail_1_0_0_Folder::special($_POST['account'], $folder);
			$data = Mail_1_0_0_Account::get($_POST['account']);

			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'conf.subscribe' : #(Un)subscribe a folder
			$result = Mail_1_0_0_Folder::subscribe($_POST['folder'], $_POST['mode']);
			$data = Mail_1_0_0_Folder::get(Mail_1_0_0_Folder::account($_POST['folder']), false);

			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'conf.set' : #Save account information
			$option = $_POST;
			unset($option['account']);

			print $system->xml_send(Mail_1_0_0_Account::set($_POST['account'], $option));
		break;

		case 'gui.load' : print Mail_1_0_0_Item::image($_GET['cid']); break; #Load an embedded image

		case 'gui.show' : #Get message body of a mail and return as is
			$system->cache_header(); #Cache mail body
			print Mail_1_0_0_Item::show($_GET['message']);
		break;

		case 'gui._body' : #Download the attachment
			#NOTE : Set an attachment header in all cases not to let errors make browser shift into a new page
			header('Content-Type: text/plain');
			header('Content-Disposition: attachment');

			print Mail_1_0_0_Item::attachment($_GET['id']); #Get the attachment file from the mail server
		break;

		case 'gui.compose' : print Mail_1_0_0_Item::text($_GET['id']); break; #Get the plain text version of a mail

		case 'gui.send' : #Send out a mail (Send the result status as a plain text to be grabbed inside 'iframe')
			print Mail_1_0_0_Item::send($_POST['account'], $_POST['subject'], $_POST['body'], $_POST['to'], $_POST['cc'], $_POST['bcc'], $_POST['source'], $_FILES);
		break;

		case 'item.get' : #Get list of mails stored in the database
			if($_GET['update']) $update = Mail_1_0_0_Item::update($_GET['folder']); #Update it from the mail server
			$data = Mail_1_0_0_Item::get($_GET['folder'], $_GET['page'], $_GET['order'], $_GET['reverse'], $_GET['marked'], $_GET['unread'], $_GET['search']); #Get list from database

			print $system->xml_send($update !== false && $data !== false, $data, null, true);
		break;

		case 'item.mark' : print $system->xml_send(Mail_1_0_0_Item::flag($_POST['id'], 'Flagged', !!$_POST['mode'])); break; #Mark mails

		case 'item.move' : print $system->xml_send(Mail_1_0_0_Item::move($_POST['id'], $_POST['folder'])); break; #Move mails

		case 'item.trash' : print $system->xml_send(Mail_1_0_0_Item::special($_POST['id'], 'trash', $_POST['account'])); break; #Move mails to trash
	}
?>

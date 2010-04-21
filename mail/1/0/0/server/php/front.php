<?php
	$system = new System_1_0_0(__FILE__);
	$system->cache_header(0); #Avoid caching server response (Cache on client side instead)

	switch($_GET['task'])
	{
		case 'account.get' : #Get list of accounts
			$data = Mail_1_0_0_Account::get();
			print $system->xml_dump($data !== false, 'account', $data, array('user', 'supported'));
		break;

		case 'account.remove' : #Remove an account
			$result = Mail_1_0_0_Account::remove($_POST['id']);
			print $system->xml_dump($result);
		break;

		case 'address.list' : #Load addresses having mail addresses
			$data = Mail_1_0_0_Address::load($_GET['group'], $_GET['type']);
			print $system->xml_dump($data !== false, 'address', $data);
		break;

		case 'address.open' : #Load address groups
			$data = Mail_1_0_0_Address::group();
			print $system->xml_dump($data !== false, 'group', $data);
		break;

		case 'conf.create' : #Create a folder
			$result = Mail_1_0_0_Folder::create($_POST['account'], $_POST['parent'], $_POST['name']);
			$data = Mail_1_0_0_Folder::get($_POST['account'], false, true);

			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'conf.folder' : case 'folder.get' : #Get list of folders for an account
			$result = $_GET['update'] ? Mail_1_0_0_Folder::update($_GET['account'], $_GET['update'] == 2) : true;
			$data = Mail_1_0_0_Folder::get($_GET['account'], $_GET['subscribed'], true);

			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'conf.move' : #Move a folder
			$result = Mail_1_0_0_Folder::move($_POST['folder'], $_POST['target']);
			$data = Mail_1_0_0_Folder::get(Mail_1_0_0_Folder::account($_POST['folder']), false, true);

			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'conf.rename' : #Rename a folder
			$result = Mail_1_0_0_Folder::rename($_POST['folder'], $_POST['name']);
			$data = Mail_1_0_0_Folder::get(Mail_1_0_0_Folder::account($_POST['folder']), false, true);

			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'conf.remove' : #Delete folders
			$account = Mail_1_0_0_Folder::account($_POST['folder'][0]);
			$result = Mail_1_0_0_Folder::remove($_POST['folder'], true);

			$data = Mail_1_0_0_Folder::get($account, false, true);
			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'conf.set' : #Save account information
			$option = $_POST;
			unset($option['account']);

			$result = Mail_1_0_0_Account::set($_POST['account'], $option);
			print $system->xml_dump($result);
		break;

		case 'conf.special' : #Set special folders
			$folder = $_POST;
			unset($folder['account']);

			$result = Mail_1_0_0_Folder::special($_POST['account'], $folder);
			$data = Mail_1_0_0_Account::get($_POST['account']);

			print $system->xml_dump($result !== false && $data !== false, 'account', $data, array('user'));
		break;

		case 'conf.subscribe' : #(Un)subscribe a folder
			$result = Mail_1_0_0_Folder::subscribe($_POST['folder'], $_POST['mode']);
			$data = Mail_1_0_0_Folder::get(Mail_1_0_0_Folder::account($_POST['folder']), false, true);

			print $system->xml_send($result !== false && $data !== false, $data);
		break;

		case 'folder.empty' : #Empty trash
			$result = Mail_1_0_0_Folder::purge($_POST['account']);
			print $system->xml_dump($result);
		break;

		case 'gui._body.attachment' : #Download the attachment
			#NOTE : Set an attachment header in all cases not to let errors make browser shift into a new page
			#NOTE : Not sending caching header under errors in case of occasions like mail server unavailability for that moment
			#NOTE : Be warned that if an error is generated and printed, the attachment will be a text file displaying the errors
			header('Content-Type: text/plain');
			header('Content-Disposition: attachment; filename=empty.txt');

			print Mail_1_0_0_Item::attachment($_GET['id']); #Get the attachment file from the mail server
		break;

		case 'gui.compose' : #Get the plain text version of a mail
			$data = Mail_1_0_0_Item::text($_GET['id']);

			$system->cache_header(); #Cache mail body
			if($system->compress_header()) $data = gzencode($data); #If gzip compression is allowed, send back compressed

			header('Content-Type: text/html; charset=utf-8');
			header('Content-Length: '.strlen($data));

			print $data;
		break;

		case 'gui.load' : #Load an embedded image
			$system->cache_header(); #Cache attachment data on the client side
			print Mail_1_0_0_Item::image($_GET['id'], $_GET['cid']);
		break;

		case 'gui.send' : #Send out a mail (Send the result status as a plain text to be grabbed inside 'iframe')
			print Mail_1_0_0_Item::send($_POST['account'], $_POST['subject'], $_POST['body'], $_POST['to'], $_POST['cc'], $_POST['bcc'], $_POST['source'], $_FILES, $_POST['draft'], $_POST['resume']);
		break;

		case 'gui._body' : #Get message body of a mail and return as is
			$system->cache_header(); #Cache mail body
			print Mail_1_0_0_Item::show($_GET['message']);
		break;

		case 'gui.show' : #Mark a mail as read
			$result = Mail_1_0_0_Item::flag(array($_POST['id']), 'Seen', true);
			print $system->xml_dump($result);
		break;

		case 'item.get' : #Get list of mails stored in the database
			$update = $_GET['update'] ? Mail_1_0_0_Item::update($_GET['folder']) : true; #Update it from the mail server
			$data = Mail_1_0_0_Item::get($_GET['folder'], $_GET['page'], $_GET['order'], $_GET['reverse'], $_GET['marked'], $_GET['unread'], $_GET['search']); #Get list from database

			$xml = $system->xml_node('page', array('total' => $data['page']));

			if(is_array($data['list']))
			{
				foreach($data['list'] as $row) #Construct the mail XML
				{
					$body = '';
					$attributes = array();

					foreach($row as $key => $value)
					{
						if(is_array($value)) foreach($value as $address) $body .= $system->xml_node($key, $address);
						elseif($key == 'preview') $body .= $system->xml_node($key, null, $system->xml_data($value));
						else $attributes[$key] = $value;
					}

					$xml .= $system->xml_node('mail', $attributes, $body);
				}
			}

			print $system->xml_send($update !== false && $data !== false, $xml, null, true);
		break;

		case 'item.mark' : #Mark mails
			$result = Mail_1_0_0_Item::flag($_POST['id'], 'Flagged', !!$_POST['mode']);
			print $system->xml_dump($result);
		break;

		case 'item.move' : #Move mails
			$result = Mail_1_0_0_Item::move($_POST['id'], $_POST['folder']);
			print $system->xml_dump($result);
		break;

		case 'item.show' : #Get mail account and folder
			$data = Mail_1_0_0_Item::folder($_GET['id']);
			print $system->xml_send($data !== false, $system->xml_node('mail', $data));
		break;

		case 'item.trash' : #Trash mails
			$result = Mail_1_0_0_Item::special($_POST['id'], 'trash', $_POST['account']);
			print $system->xml_dump($result);
		break;
	}
?>

<?php
	class Mail_1_0_0_Folder
	{
		public static function get($account, System_1_0_0_User $user = null) #Get list of folders for an account
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			list($connection, $host, $parameter) = Mail_1_0_0_Account::connect($account, '', $user); #Connect to the server
			$list = ''; #List of folders

			$folders = imap_list($connection, '{'.$host.'}', '*');
			sort($folders);

			foreach($folders as $box) #Get list of all mail boxes
			{
				$box = preg_replace('/^{.+?}/', '', $box); #Get the mail box name
				imap_reopen($connection, $parameter.$box); #Reconnect to that mail box

				$content = imap_check($connection); #Check the box information
				if(!$content) $log->user(LOG_ERR, "Invalid mailbox '$box'", 'Check the mail server or configuration');

				$list .= $system->xml_node('folder', array('name' => $box, 'count' => $content->Nmsgs, 'recent' => $content->Recent));
			}

			return $list;
		}
	}
?>

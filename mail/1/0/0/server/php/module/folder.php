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

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT id, name FROM {$database->prefix}folder WHERE user = :user AND account = :account");
			$query->run(array(':user' => $user->id, ':account' => $account));

			if(!$query->success) return false;
			foreach($query->all() as $row) $xml .= $system->xml_node('folder', $row);

			return $xml;
		}

		public static function name($id, System_1_0_0_User $user = null) #Get the name of a folder from its ID
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT name FROM {$database->prefix}folder WHERE id = :id AND user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id));

			if(!$query->success) return false;
			return $query->column();
		}

		public static function update($account, System_1_0_0_User $user = null) #Update list of folders
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT receive_type FROM {$database->prefix}account WHERE user = :user AND id = :id");
			$query->run(array(':user' => $user->id, ':id' => $account));

			if(!$query->success) return false;
			$type = $query->column();

			if($type != 'imap' && $type != 'gmail') return true; #Sync folders for IMAP
			$query = array();

			#Delete non existing folder
			$query['delete'] = $database->prepare("DELETE FROM {$database->prefix}folder WHERE id = :id AND user = :user");

			#Delete mails that existed in the non existing folder
			$query['purge'] = $database->prepare("DELETE FROM {$database->prefix}mail WHERE user = :user AND account = :account AND folder = :folder");

			#Save a new folder
			$query['insert'] = $database->prepare("INSERT INTO {$database->prefix}folder (user, account, name) VALUES (:user, :account, :name)");

			$query['select'] = $database->prepare("SELECT id, name FROM {$database->prefix}folder WHERE user = :user AND account = :account");
			$query['select']->run(array(':user' => $user->id, ':account' => $account));

			if(!$query['select']->success) return false;
			$folders = $stored = array(); #List of folders

			foreach($query['select']->all() as $row)
			{
				$stored[] = $row['name'];
				$index[$row['name']] = $row['id'];
			}

			list($connection, $host, $parameter, $type) = Mail_1_0_0_Account::connect($account, '', $user); #Connect to the server
			$list = ''; #List of folders

			#Get the folder names
			foreach(imap_list($connection, '{'.$host.'}', '*') as $name) $folders[] = preg_replace('/^{'.preg_quote($host).'}/', '', $name);

			foreach(array_diff($stored, $folders) as $name) #For folders not existing anymore, delete it and the mails inside
			{
				$query['delete']->run(array(':id' => $index[$name], ':user' => $user->id));
				$query['purge']->run(array(':user' => $user->id, ':account' => $account, ':folder' => $index[$name]));
			}

			foreach(array_diff($folders, $stored) as $name) #For newly added folders, add them
				$query['insert']->run(array(':user' => $user->id, ':account' => $account, ':name' => $name));

			return true;
		}
	}
?>

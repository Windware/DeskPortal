<?php
	class Mail_1_0_0_Folder
	{
		private static $_id = array(); #Folder ID

		protected static function _list(&$system, $structure) #Create list of XML from multi dimensional array
		{
			static $index = 0;

			foreach($structure as $name => $part) #Concatenate XML entries for each folders
				$xml .= $system->xml_node('folder', array('id' => self::$_id[$index++], 'name' => $name), self::_list($system, $part));

			return $xml;
		}

		public static function create($account, $name, System_1_0_0_User $user = null) #Create a new folder
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account) || !$system->is_text($name)) return $log->param();
			if(strlen($name) > 255) return $log->dev(LOG_WARNING, 'Folder name too long', 'Specify a shorter name below 255 characters'); #TODO - Real max length is?

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$link = Mail_1_0_0_Account::connect($account, '', $user);

			$exist = imap_getmailboxes($link['connection'], '{'.$link['host'].'}', '*'); #Existing mail boxes
			if(!is_array($exist)) return Mail_1_0_0_Account::error($link['host']);

			$target = '{'.$link['host'].'}'.imap_utf7_encode($name); #Target name to create
			foreach($exist as $box) if($box->name == $target) $built = true; #Check if already existing

			if(!$built)
			{
				$op = imap_createmailbox($link['connection'], $target); #Create the mailbox remotely
				if(!$op) return Mail_1_0_0_Account::error($link['host']);

				$op = imap_subscribe($link['connect'], $target); #Subscribe to it
				if(!$op) return Mail_1_0_0_Account::error($link['host']);
			}

			$query = $database->prepare("SELECT id FROM {$database->prefix}folder WHERE user = :user AND account = :account AND name = :name");
			$query->run(array(':user' => $user->id, ':account' => $account, ':name' => $name));

			if(!$query->success) return false;
			if($id = $query->column()) return $id; #Return ID if already created

			$query = $database->prepare("INSERT INTO {$database->prefix}folder (user, account, name) VALUES (:user, :account, :name)");
			$query->run(array(':user' => $user->id, ':account' => $account, ':name' => $name));

			if(!$query->success) return false;
			return $database->id(); #Return the created ID
		}

		public static function get($account, System_1_0_0_User $user = null) #Get list of folders for an account
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT id, name, separator FROM {$database->prefix}folder WHERE user = :user AND account = :account ORDER BY LOWER(name)");
			$query->run(array(':user' => $user->id, ':account' => $account));

			if(!$query->success) return false;
			$list = $query->all();

			$structure = array(); #Folder structure

			foreach($list as $index => $row) #For all folders
			{
				self::$_id[$index] = $row['id']; #Remember folder ID

				$name = $row['separator'] ? explode($row['separator'], $row['name']) : array($row['name']); #Separate the folder name
				$dimension = &$structure;

				foreach($name as $part) #Drop the names into multi dimensional array
				{
					if(!isset($dimension[$part])) $dimension[$part] = array();
					$dimension = &$dimension[$part];
				}
			}

			return self::_list($system, $structure); #Dump the array into XML
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

			return $query->success ? $query->column() : false;
		}

		public static function update($account, System_1_0_0_User $user = null) #Update list of folders
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT receive_type FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $account, ':user' => $user->id));

			if(!$query->success) return false;

			if(Mail_1_0_0_Account::type($query->column()) != 'imap') return true; #Do not try to sync folders if not IMAP
			$link = Mail_1_0_0_Account::connect($account, '', $user); #Connect to the server

			$subscribed = imap_getsubscribed($link['connection'], '{'.$link['host'].'}', '*'); #Get subscribed folder names
			if(!$subscribed) return Mail_1_0_0_Account::error($link['host']);

			$id = $folders = $stored = $separator = $parent = $remove = $target = array();

			foreach($subscribed as $info)
			{
				$name = $folders[] = preg_replace('/^{'.preg_quote($link['host']).'}/', '', imap_utf7_decode($info->name));

				$separator[$name] = $info->delimiter; #Keep folder delimiter character
				$parent[$name] = $info->attributes & LATT_NOINFERIORS == $info->attributes ? 0 : 1; #Find if it can have child folders
			}

			$query = $database->prepare("SELECT id, name FROM {$database->prefix}folder WHERE user = :user AND account = :account");
			$query->run(array(':user' => $user->id, ':account' => $account));

			if(!$query->success) return false;

			foreach($query->all() as $row) #Keep name and ID relation
			{
				$stored[] = $row['name'];
				$id[$row['name']] = $row['id'];
			}

			$param = array(':user' => $user->id);

			foreach(array_diff($stored, $folders) as $index => $name) #For folders not existing anymore
			{
				$value = $target[] = ":id_{$index}_index";
				$param[$value] = $id[$name];
			}

			if(count($target))
			{
				$target = implode(', ', $target);
				$database->begin();

				$query = $database->prepare("SELECT id FROM {$database->prefix}mail WHERE user = :user AND folder IN ($target)");
				$query->run($param); #Select the mails belonging to the missing folders

				if(!$query->success) return false;

				foreach($query->all() as $row) $remove[] = $row['id'];
				Mail_1_0_0_Item::remove($remove, true, $user); #Remove them all from local database

				$query = $database->prepare("DELETE FROM {$database->prefix}folder WHERE id IN ($target) AND user = :user");
				$query->run($param); #Delete non existing folders

				if(!$query->success) return false;
				$database->commit();
			}

			$query = $database->prepare("INSERT INTO {$database->prefix}folder (user, account, name, parent, separator) VALUES (:user, :account, :name, :parent, :separator)");

			foreach(array_diff($folders, $stored) as $name) #For newly added folders, add them
			{
				$query->run(array(':user' => $user->id, ':account' => $account, ':name' => $name, ':parent' => $parent[$name], ':separator' => $separator[$name]));
				if(!$query->success) return false;
			}

			return true;
		}
	}
?>

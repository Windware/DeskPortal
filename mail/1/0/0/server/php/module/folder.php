<?php
	class Mail_1_0_0_Folder
	{
		private static $_id = array(); #Folder ID

		private static $_subscribed = array(); #Subscribed folders

		protected static function _list(&$system, $structure, $subscribed = true) #Create list of XML from multi dimensional array
		{
			static $index = -1;

			foreach($structure as $name => $part) #Concatenate XML entries for each folders
			{
				$index++;
				if($subscribed && !self::$_subscribed[$index]) continue;

				$info = array('name' => $name, 'id' => self::$_id[$index], 'subscribed' => self::$_subscribed[$index]);
				$xml .= $system->xml_node('folder', $info, self::_list($system, $part, $subscribed));
			}

			return $xml;
		}

		public static function account($folder, System_1_0_0_User $user = null) #Get account ID from a folder ID
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($folder)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT account FROM {$database->prefix}folder WHERE id = :id AND user = :user");
			$query->run(array(':id' => $folder, ':user' => $user->id));

			return $query->success ? $query->column() : false;
		}

		public static function create($account, $parent, $name, System_1_0_0_User $user = null) #Create a new folder
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account) || !$system->is_text($name)) return $log->param();
			if(strlen($name) > 255) return $log->dev(LOG_WARNING, 'Folder name too long', 'Specify a shorter name below 255 characters'); #TODO - Real max length is?

			if(!$system->is_digit($parent)) $parent = null;

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT receive_type FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $account, ':user' => $user->id));

			if(!$query->success) return false;
			$type = Mail_1_0_0_Account::type($query->column());

			#TODO - Possibly check if the folder name has a separator character inside and return error?
			if($parent) #If a parent folder is specified
			{
				$query = $database->prepare("SELECT name, parent, separator FROM {$database->prefix}folder WHERE id = :id AND user = :user");
				$query->run(array(':id' => $parent, ':user' => $user->id));

				if(!$query->success) return false;
				$parent = $query->row();

				if(!$parent['parent']) return false; #If it cannot have any child folders, quit

				if($type == 'pop3') $parent['separator'] = '/'; #For POP3, use a generic separator value
				elseif(!strlen($parent['separator'])) #If no separator is found for the parent folder
				{
					$query = $database->prepare("SELECT separator FROM {$database->prefix}folder WHERE user = :user AND account = :account AND separator IS NOT NULL LIMIT 1");
					$query->run(array(':user' => $user->id, ':account' => $account));

					if(!$query->success) return false;
					$parent['separator'] = $query->column(); #Find the separator used for the account

					if(!strlen($parent['separator'])) return false; #Give up
				}
			}
			else $parent = array(); #Make it empty

			if($type == 'imap') #For IMAP, create the folder remotely
			{
				$link = Mail_1_0_0_Account::connect($account, '', $user);
				if(!$link) return false;

				$exist = imap_getmailboxes($link['connection'], '{'.$link['host'].'}', '*'); #Existing mail boxes
				if(!is_array($exist)) return Mail_1_0_0_Account::error($link['host']);

				$target = '{'.$link['host'].'}'.mb_convert_encoding($parent['name'].$parent['separator'].$name, 'UTF7-IMAP', 'UTF-8'); #Target name to create

				foreach($exist as $box)
				{
					$separator = $box->delimiter; #Keep a folder delimiter
					if(strtolower($box->name) == strtolower($target)) $built = true; #Check if already existing
				}

				if(!$built)
				{
					$op = imap_createmailbox($link['connection'], $target); #Create the mailbox remotely
					if(!$op) return Mail_1_0_0_Account::error($link['host']);

					$op = imap_subscribe($link['connection'], $target); #Subscribe to it
					if(!$op) return Mail_1_0_0_Account::error($link['host']);
				}
			}

			$query = $database->prepare("SELECT id FROM {$database->prefix}folder WHERE user = :user AND account = :account AND name = :name");
			$query->run(array(':user' => $user->id, ':account' => $account, ':name' => $name));

			if(!$query->success) return false;
			if($id = $query->column()) return $id; #Return ID if already created

			$query = $database->prepare("INSERT INTO {$database->prefix}folder (user, account, name, parent, separator, subscribed) VALUES (:user, :account, :name, :parent, :separator, :subscribed)");
			$query->run(array(':user' => $user->id, ':account' => $account, ':name' => $name, ':parent' => 1, ':separator' => $separator, ':subscribed' => 1));

			if(!$query->success) return false;
			return $database->id(); #Return the created ID
		}

		public static function get($account, $subscribed = true, System_1_0_0_User $user = null) #Get list of folders for an account
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$param = array(':user' => $user->id, ':account' => $account);

			$query = $database->prepare("SELECT id, name, separator, subscribed FROM {$database->prefix}folder WHERE user = :user AND account = :account ORDER BY LOWER(name)");
			$query->run($param);

			if(!$query->success) return false;
			$list = $query->all();

			$structure = array(); #Folder structure

			foreach($list as $index => $row) #For all folders
			{
				self::$_id[$index] = $row['id']; #Remember folder ID
				self::$_subscribed[$index] = $row['subscribed']; #Remember folder ID

				$name = $row['separator'] ? explode($row['separator'], $row['name']) : array($row['name']); #Separate the folder name
				$dimension = &$structure;

				foreach($name as $part) #Drop the names into multi dimensional array
				{
					if(!isset($dimension[$part])) $dimension[$part] = array();
					$dimension = &$dimension[$part];
				}
			}

			return self::_list($system, $structure, $subscribed); #Dump the array into XML
		}

		public static function move($id, $target, System_1_0_0_User $user = null) #Move a folder
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id)) return $log->param();
			if(!$system->is_digit($target)) $target = 0;

			if($id == 0 || $id == $target) return true;

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT account, id, name, separator, subscribed FROM {$database->prefix}folder WHERE id = :id AND user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id));

			if(!$query->success) return false;
			$folder = array('source' => $query->row());

			if(!count($folder['source'])) return false;

			if($target != 0) #If a destination folder is specified
			{
				$query->run(array(':id' => $target, ':user' => $user->id));
				if(!$query->success) return false;

				$folder['target'] = $query->row();
				if(!count($folder['target'])) return false;

				if($folder['source']['account'] != $folder['target']['account']) return false; #Do not allow cross account move for this version
			}

			if(strlen($folder['source']['separator'])) #If the source folder has a delimiter defined
			{
				$tree = $folder['source']['name'].$folder['source']['separator']; #Matching folder tree for child folders

				$query = $database->prepare("SELECT id, name, separator, subscribed FROM {$database->prefix}folder WHERE user = :user AND account = :account AND name LIKE :tree $database->escape");
				$query->run(array(':user' => $user->id, ':account' => $folder['source']['account'], ':tree' => $system->database_escape($tree).'%')); #Find child folders

				$children = $query->all();
				if(!$query->success) return false;

				$separator = preg_quote($folder['source']['separator']);
				$new = preg_replace("/^.+$separator([^$separator]*)$/", '\1', $folder['source']['name']);
			}
			else $new = $folder['source']['name'];

			$separator = strlen($folder['target']['separator']) ? $folder['target']['separator'] : $folder['source']['separator'];
			if(!strlen($separator) || !strlen($new)) return false; #If a separator cannot be found, quit

			if($target != 0) $new = $folder['target']['name'].$separator.$new; #Full path of the new folder name
			$database->begin(); #Make it all atomic

			$query = $database->prepare("UPDATE {$database->prefix}folder SET name = :name WHERE id = :id AND user = :user");
			$query->run(array(':name' => $new, ':id' => $folder['source']['id'], ':user' => $user->id)); #Update the specified folder

			$update = array();

			if(is_array($children)) #If the source folder had a delimiter defined to look for child folders
			{
				foreach($children as $row) #Update the child folders in the database
				{
					$update[$row['id']] = $name = preg_replace('/^'.preg_quote($tree).'/', $new.$separator, $row['name']);
					$query->run(array(':name' => $name, ':id' => $row['id'], ':user' => $user->id)); #Change the folder name

					if($query->success) continue;

					$database->rollback();
					return false;
				}
			}

			$query = $database->prepare("SELECT receive_type FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $folder['source']['account'], ':user' => $user->id)); #Pick the account type

			if(!$query->success) return false;

			if(Mail_1_0_0_Account::type($query->column()) != 'imap') return $database->commit();
			$link = Mail_1_0_0_Account::connect($folder['source']['account'], '', $user); #Connect to the server

			if(!$link)
			{
				$database->rollback();
				return false;
			}

			$from = '{'.$link['host'].'}'.mb_convert_encoding($folder['source']['name'], 'UTF7-IMAP', 'UTF-8');
			$to = '{'.$link['host'].'}'.mb_convert_encoding($new, 'UTF7-IMAP', 'UTF-8');

			#Rename on server (Child folders will be automatically renamed to follow its parent folder)
			if(!imap_renamemailbox($link['connection'], $from, $to) || $folder['source']['subscribed'] && !imap_subscribe($link['connection'], $to))
			{
				$database->rollback();
				return Mail_1_0_0_Account::error($link['host']);
			}

			foreach($children as $row) #Update the subscribed status
			{
				if(!$row['subscribed']) continue;

				$name = '{'.$link['host'].'}'.mb_convert_encoding($update[$row['id']], 'UTF7-IMAP', 'UTF-8');
				if(imap_subscribe($link['connection'], $name)) continue;

				$database->rollback();
				return Mail_1_0_0_Account::error($link['host']);
			}

			return $database->commit();
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

		public static function remove($folder, $remote = false, System_1_0_0_User $user = null) #Delete folders
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!is_array($folder)) return $log->param();
			foreach($folder as $id) if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$target = array(); #List of folders to delete
			$query = array();

			$query['base'] = $database->prepare("SELECT id, account, name, separator FROM {$database->prefix}folder WHERE id = :id AND user = :user");
			$query['child'] = $database->prepare("SELECT id, name FROM {$database->prefix}folder WHERE user = :user AND account = :account AND name LIKE :name $database->escape");

			$query['mail'] = $database->prepare("SELECT id FROM {$database->prefix}mail WHERE user = :user AND folder = :folder");
			if($remote) $query['type'] = $database->prepare("SELECT receive_type FROM {$database->prefix}account WHERE id = :id AND user = :user");

			$param = array(':user' => $user->id);
			$count = 0;

			foreach($folder as $id) #For all the given folders
			{
				$query['base']->run(array(':id' => $id, ':user' => $user->id)); #Find the folder info
				if(!$query['base']->success) return false;

				$base = $query['base']->row();
				if(!$base) continue; #If not found, ignore

				$query['mail']->run(array(':user' => $user->id, ':folder' => $base['id']));
				if(!$query['mail']->success) return false;

				$delete = array(); #List of mails to delete
				foreach($query['mail']->all() as $mail) $delete[] = $mail['id'];

				if(!Mail_1_0_0_Item::remove($delete)) return false; #Remove the mails in this folder

				$value = $target[] = ':i'.(++$count).'d'; #List the folder ID to delete
				$param[$value] = $base['id'];

				$query['child']->run(array(':user' => $user->id, ':account' => $base['account'], ':name' => $system->database_escape($base['name'].$base['separator']).'%')); #Find the child folders
				if(!$query['child']->success) return false;

				if($remote) #If attempting to delete remote folders
				{
					$query['type']->run(array(':id' => $base['account'], ':user' => $user->id)); #Pick the account type
					if(!$query['type']->success) return false;

					$type = Mail_1_0_0_Account::type($query['type']->column());
					$link = Mail_1_0_0_Account::connect($base['account'], '', $user); #Connect and remove the mail box
				}

				foreach($query['child']->all() as $row) #For all the child folders
				{
					$query['mail']->run(array(':user' => $user->id, ':folder' => $row['id']));
					if(!$query['mail']->success) return false;

					$delete = array(); #List of mails to delete
					foreach($query['mail']->all() as $mail) $delete[] = $mail['id'];

					if(!Mail_1_0_0_Item::remove($delete)) return false; #Remove the mails in this folder

					$value = $target[] = ':i'.(++$count).'d'; #List the folder ID to delete
					$param[$value] = $row['id'];

					if(!$remote || $type != 'imap') continue; #For deleting folders on IMAP server
					$box = '{'.$link['host'].'}'.mb_convert_encoding($row['name'], 'UTF7-IMAP', 'UTF-8');

					$op = imap_unsubscribe($link['connection'], $box); #Unsubscribe
					if(!$op) return Mail_1_0_0_Account::error($link['host']);

					$op = imap_deletemailbox($link['connection'], $box); #Delete
					if(!$op) return Mail_1_0_0_Account::error($link['host']);
				}

				if(!$remote || $type != 'imap') continue; #If attempting to delete remote folders on IMAP
				$box = '{'.$link['host'].'}'.mb_convert_encoding($base['name'], 'UTF7-IMAP', 'UTF-8');

				$op = imap_unsubscribe($link['connection'], $box); #Unsubscribe
				if(!$op) return Mail_1_0_0_Account::error($link['host']);

				$op = imap_deletemailbox($link['connection'], $box); #Delete
				if(!$op) return Mail_1_0_0_Account::error($link['host']);
			}

			$target = implode(', ', $target);

			$query = $database->prepare("DELETE FROM {$database->prefix}folder WHERE id IN ($target) AND user = :user");
			$query->run($param); #Delete the folders : NOTE - Cannot put this function in a single transaction since 'Mail_1_0_0_Item::remove' has transaction

			return $query->success;
		}

		public static function rename($folder, $name, System_1_0_0_User $user = null) #Rename a folder
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($folder) || !is_string($name)) return $log->param();
			if(!strlen($name) || strlen($name) > 255) return false; #TODO - Show better error, the max folder name length is?

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT account, id, name, separator, subscribed FROM {$database->prefix}folder WHERE id = :id AND user = :user");
			$query->run(array(':id' => $folder, ':user' => $user->id)); #Find the folder info

			if(!$query->success) return false;

			$current = $query->row();
			if($current['name'] == $name) return true; #If nothing to rename, quit

			$tree = $current['name'].$current['separator']; #Matching folder tree for child folders

			$query = $database->prepare("SELECT id, name, separator, subscribed FROM {$database->prefix}folder WHERE user = :user AND account = :account AND name LIKE :tree $database->escape");
			$query->run(array(':user' => $user->id, ':account' => $current['account'], ':tree' => $system->database_escape($tree).'%')); #Find child folders

			$children = $query->all();
			if(!$query->success) return false;

			if(strlen($current['separator'])) #If it has a folder delimiter
			{
				$partial = array_slice(explode($current['separator'], $current['name']), 0, -1);
				array_push($partial, $name);

				$target = implode($current['separator'], $partial); #New full folder path
			}
			else $target = $name;

			$database->begin(); #Make it all atomic

			$query = $database->prepare("UPDATE {$database->prefix}folder SET name = :name WHERE id = :id AND user = :user");
			$query->run(array(':name' => $target, ':id' => $folder, ':user' => $user->id)); #Update the specified folder

			$update = array();

			foreach($children as $row) #Update the child folders in the database
			{
				$name = preg_replace('/^'.preg_quote($tree).'/', $target.$current['separator'], $row['name']);
				$query->run(array(':name' => $name, ':id' => $row['id'], ':user' => $user->id)); #Change the folder name

				$update[$row['id']] = $name; #Remember the new name

				if(!$query->success)
				{
					$database->rollback();
					return false;
				}
			}

			$query = $database->prepare("SELECT receive_type FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $current['account'], ':user' => $user->id)); #Pick the account type

			if(!$query->success) return false;

			if(Mail_1_0_0_Account::type($query->column()) != 'imap') return $database->commit(); #Rename on server for IMAP (Child folders will be automatically renamed)
			$link = Mail_1_0_0_Account::connect($current['account'], '', $user); #Connect to the server

			if(!$link)
			{
				$database->rollback();
				return false;
			}

			$from = '{'.$link['host'].'}'.mb_convert_encoding($current['name'], 'UTF7-IMAP', 'UTF-8');
			$to = '{'.$link['host'].'}'.mb_convert_encoding($target, 'UTF7-IMAP', 'UTF-8');

			if(!imap_renamemailbox($link['connection'], $from, $to) || $current['subscribed'] && !imap_subscribe($link['connection'], $to)) #Rename on server
			{
				$database->rollback();
				return Mail_1_0_0_Account::error($link['host']);
			}

			foreach($children as $row) #Update the subscribed status
			{
				if(!$row['subscribed']) continue;

				$name = '{'.$link['host'].'}'.mb_convert_encoding($update[$row['id']], 'UTF7-IMAP', 'UTF-8');
				if(imap_subscribe($link['connection'], $name)) continue;

				$database->rollback();
				return Mail_1_0_0_Account::error($link['host']);
			}

			return $database->commit();
		}

		public static function special($account, $folder, System_1_0_0_User $user = null) #Set special folders
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account) || !is_array($folder)) return $log->param();
			if(!$account) return false;

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$special = array('drafts', 'sent', 'trash'); #Special folders
			$param = array(':id' => $account, ':user' => $user->id);

			$section = array();

			foreach($folder as $name => $id)
			{
				if(!in_array($name, $special) || !$system->is_digit($id)) return $log->param();

				$section[] = "folder_$name = :folder_$name";
				$param[":folder_$name"] = $id;
			}

			$section = implode(', ', $section);

			$query = $database->prepare("UPDATE {$database->prefix}account SET $section WHERE id = :id AND user = :user");
			$query->run($param);

			return $query->success;
		}

		public static function subscribe($folder, $mode, System_1_0_0_User $user = null) #(Un)subscribe a folder
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($folder)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT receive_type, account.id FROM {$database->prefix}folder as folder, {$database->prefix}account as account WHERE folder.id = :id AND folder.user = :user AND folder.account = account.id AND account.user = :user");
			$query->run(array(':id' => $folder, ':user' => $user->id));

			if(!$query->success) return false;

			$info = $query->row();
			if(Mail_1_0_0_Account::type($info['receive_type']) != 'imap') return false; #Ignore if not IMAP

			$query = $database->prepare("SELECT name FROM {$database->prefix}folder WHERE id = :id AND user = :user");
			$query->run(array(':id' => $folder, ':user' => $user->id));

			if(!$query->success) return false;
			$name = mb_convert_encoding($query->column(), 'UTF7-IMAP', 'UTF-8');

			if(!$name) return false;
			$database->begin();

			$query = $database->prepare("UPDATE {$database->prefix}folder SET subscribed = :subscribed WHERE id = :id AND user = :user");
			$query->run(array(':subscribed' => $mode ? 1 : 0, ':id' => $folder, ':user' => $user->id));

			if(!$query->success) return false;

			$link = Mail_1_0_0_Account::connect($info['id'], '', $user); #Connect to the server
			$op = $mode ? imap_subscribe($link['connection'], '{'.$link['host']."}$name") : imap_unsubscribe($link['connection'], '{'.$link['host']."}$name");

			if($op) return $database->commit();

			$database->rollback();
			return Mail_1_0_0_Account::error($link['host']);
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

			$all = imap_getmailboxes($link['connection'], '{'.$link['host'].'}', '*'); #Get subscribed folder names
			if(!$all) return Mail_1_0_0_Account::error($link['host']);

			$subscribed = imap_getsubscribed($link['connection'], '{'.$link['host'].'}', '*'); #Get subscribed folder names
			if(!$subscribed) return Mail_1_0_0_Account::error($link['host']);

			$id = $folders = $stored = $separator = $parent = $read = $remove = $target = $used = array();
			foreach($subscribed as $info) $used[] = $info->name; #Get list of subscribed folder names

			foreach($all as $info)
			{
				$name = $folders[] = preg_replace('/^{'.preg_quote($link['host']).'}/', '', mb_convert_encoding($info->name, 'UTF-8', 'UTF7-IMAP'));

				$separator[$name] = $info->delimiter; #Keep folder delimiter character
				$parent[$name] = $info->attributes & LATT_NOINFERIORS == $info->attributes ? 0 : 1; #Find if it can have child folders

				$read[$name] = in_array($info->name, $used) ? 1 : 0;
			}

			$query = $database->prepare("SELECT id, name, subscribed FROM {$database->prefix}folder WHERE user = :user AND account = :account");
			$query->run(array(':user' => $user->id, ':account' => $account));

			if(!$query->success) return false;
			$local = $query->all();

			$query = $database->prepare("UPDATE {$database->prefix}folder SET subscribed = :subscribed WHERE id = :id AND user = :user");

			foreach($local as $row) #Keep name and ID relation
			{
				$stored[] = $row['name'];
				$id[$row['name']] = $row['id'];

				$mode = null;

				if($read[$row['name']]) { if(!$row['subscribed']) $mode = 1; } #If not subscribed locally but remotely, flip it
				elseif($row['subscribed']) $mode = 0; #If subscribed locally but note remotely, flip it

				if($mode === null) continue;

				$query->run(array(':subscribed' => $mode, ':id' => $row['id'], ':user' => $user->id));
				if(!$query->success) return false;
			}

			foreach(array_diff($stored, $folders) as $name) $target[] = $id[$name]; #For folders not existing anymore
			self::remove($target); #Remove them

			$query = $database->prepare("INSERT INTO {$database->prefix}folder (user, account, name, parent, separator, subscribed) VALUES (:user, :account, :name, :parent, :separator, :subscribed)");

			foreach(array_diff($folders, $stored) as $name) #For newly added folders, add them
			{
				$query->run(array(':user' => $user->id, ':account' => $account, ':name' => $name, ':parent' => $parent[$name], ':separator' => $separator[$name], ':subscribed' => $read[$name]));
				if(!$query->success) return false;
			}

			return true;
		}
	}
?>

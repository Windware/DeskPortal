<?php
	class Mail_1_0_0_Folder
	{
		private static $_folder = array(); #Folder parameters

		protected static $_separator = '/'; #Generic folder separator for POP3 accounts

		protected static function _list(&$system, $structure, $subscribed = true, $xml = false) #Create list of XML from multi dimensional array
		{
			static $index = -1;
			$list = !$xml ? array() : '';

			foreach($structure as $name => $part) #Concatenate XML entries for each folders
			{
				$index++;
				if($subscribed && !self::$_folder[$index]['subscribed']) continue;

				$info = array('name' => $name, 'id' => self::$_folder[$index]['id'], 'count' => self::$_folder[$index]['count'], 'subscribed' => self::$_folder[$index]['subscribed'], 'recent' => self::$_folder[$index]['recent']);
				$child = self::_list($system, $part, $subscribed, $xml);

				if(!$xml) $list[] = array('attributes' => $info, 'child' => $child);
				else $list .= $system->xml_node('folder', $info, $child);
			}

			return $list;
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

			if($parent) #If a parent folder is specified : TODO - Possibly check if the folder name has a separator character inside and return error?
			{
				$query = $database->prepare("SELECT name, parent, connector FROM {$database->prefix}folder WHERE id = :id AND user = :user");
				$query->run(array(':id' => $parent, ':user' => $user->id));

				if(!$query->success) return false;
				$parent = $query->row();

				if(!$parent['parent']) return false; #If it cannot have any child folders, quit

				if($type == 'pop3') $parent['connector'] = self::$_separator; #For POP3, use a generic separator value
				elseif(!strlen($parent['connector'])) #If no separator is found for the parent folder
				{
					$query = $database->prepare("SELECT connector FROM {$database->prefix}folder WHERE user = :user AND account = :account AND connector IS NOT NULL LIMIT 1");
					$query->run(array(':user' => $user->id, ':account' => $account));

					if(!$query->success) return false;
					$parent['connector'] = $query->column(); #Find the separator used for the account

					if(!strlen($parent['connector'])) return false; #Give up
				}
			}
			else $parent = array(); #Make it empty

			if($type == 'imap') #For IMAP, create the folder remotely
			{
				$link = Mail_1_0_0_Account::connect($account, '', $user);
				if(!$link) return false;

				$exist = imap_getmailboxes($link['connection'], '{'.$link['host'].'}', '*'); #Existing mail boxes
				if(!is_array($exist)) return Mail_1_0_0_Account::error($link['host']);

				$target = '{'.$link['host'].'}'.mb_convert_encoding($parent['name'].$parent['connector'].$name, 'UTF7-IMAP', 'UTF-8'); #Target name to create

				foreach($exist as $box)
				{
					$connector = $box->delimiter; #Keep a folder delimiter
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

			$query = $database->prepare("INSERT INTO {$database->prefix}folder (user, account, name, parent, connector, subscribed) VALUES (:user, :account, :name, :parent, :connector, :subscribed)");
			$query->run(array(':user' => $user->id, ':account' => $account, ':name' => $parent['name'].$parent['connector'].$name, ':parent' => 1, ':connector' => $connector, ':subscribed' => 1));

			if(!$query->success) return false;
			return $database->id(); #Return the created ID
		}

		public static function get($account, $subscribed = true, $xml = false, System_1_0_0_User $user = null) #Get list of folders for an account
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$param = array(':user' => $user->id, ':account' => $account);

			$query = $database->prepare("SELECT id, name, count, recent, connector, subscribed FROM {$database->prefix}folder WHERE user = :user AND account = :account ORDER BY LOWER(name)");
			$query->run($param);

			if(!$query->success) return false;
			$list = $query->all();

			$query = $database->prepare("SELECT receive_type FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $account, ':user' => $user->id));

			if(!$query->success) return false;
			$type = Mail_1_0_0_Account::type($query->column());

			foreach($list as $index => $row) #For all folders
			{
				self::$_folder[$index] = $row; #Remember folder parameters
				$connector = $type == 'pop3' ? self::$_separator : $row['connector']; #Use a generic separator for POP3

				$name = $connector ? explode($connector, $row['name']) : array($row['name']); #Separate the folder name
				$dimension = &$structure;

				foreach($name as $part) #Drop the names into multi dimensional array
				{
					if(!isset($dimension[$part])) $dimension[$part] = array();
					$dimension = &$dimension[$part];
				}
			}

			return self::_list($system, $structure, $subscribed, $xml); #Return an array or XML
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

			$query = $database->prepare("SELECT account, id, name, connector, subscribed FROM {$database->prefix}folder WHERE id = :id AND user = :user");
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

			if(strlen($folder['source']['connector'])) #If the source folder has a delimiter defined
			{
				$tree = $folder['source']['name'].$folder['source']['connector']; #Matching folder tree for child folders

				$query = $database->prepare("SELECT id, name, connector, subscribed FROM {$database->prefix}folder WHERE user = :user AND account = :account AND name LIKE :tree $database->escape");
				$query->run(array(':user' => $user->id, ':account' => $folder['source']['account'], ':tree' => $system->database_escape($tree).'%')); #Find child folders

				$children = $query->all();
				if(!$query->success) return false;

				$connector = preg_quote($folder['source']['connector'], '/');
				$new = preg_replace("/^.+$connector([^$connector]*)$/", '\1', $folder['source']['name']);
			}
			else $new = $folder['source']['name'];

			$connector = strlen($folder['target']['connector']) ? $folder['target']['connector'] : $folder['source']['connector'];
			if(!strlen($connector) || !strlen($new)) return false; #If a separator cannot be found, quit

			$query = $database->prepare("SELECT receive_type FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $folder['source']['account'], ':user' => $user->id)); #Pick the account type

			if(!$query->success) return false;
			if($target != 0) $new = $folder['target']['name'].$connector.$new; #Full path of the new folder name

			if($folder['source']['name'] == $new) return true; #If target is same, quit

			if(Mail_1_0_0_Account::type($query->column()) == 'imap') #For IMAP, rename on the mail server
			{
				$link = Mail_1_0_0_Account::connect($folder['source']['account'], '', $user); #Connect to the server
				if(!$link) return false;

				$from = '{'.$link['host'].'}'.mb_convert_encoding($folder['source']['name'], 'UTF7-IMAP', 'UTF-8');
				$to = '{'.$link['host'].'}'.mb_convert_encoding($new, 'UTF7-IMAP', 'UTF-8');

				if(!imap_renamemailbox($link['connection'], $from, $to) || $folder['source']['subscribed'] && !imap_subscribe($link['connection'], $to))
					return Mail_1_0_0_Account::error($link['host']); #Rename on server (Child folders will be automatically renamed to follow its parent folder)

				foreach($children as $row) #Update the subscribed status (Subscription status gets cancelled on move)
				{
					if(!$row['subscribed']) continue;

					$name = preg_replace('/^'.preg_quote($tree, '/').'/', $new.$connector, $row['name']);
					$name = '{'.$link['host'].'}'.mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8');

					#NOTE : Not stopping operation when an error occurs here
					if(!imap_subscribe($link['connection'], $name)) $log->dev(LOG_ERR, 'Failed to subscribe to a folder : '.imap_last_error(), 'Check the error');
				}
			}

			if(!$database->begin()) return false; #Make renaming all atomic

			$query = $database->prepare("UPDATE {$database->prefix}folder SET name = :name WHERE id = :id AND user = :user");
			$query->run(array(':name' => $new, ':id' => $folder['source']['id'], ':user' => $user->id)); #Update the specified folder

			if(!$query->success) return $database->rollback() && false;

			if(is_array($children)) #If the source folder had a delimiter defined, look for child folders
			{
				foreach($children as $row) #Update the child folders in the database
				{
					$name = preg_replace('/^'.preg_quote($tree, '/').'/', $new.$connector, $row['name']);

					$query->run(array(':name' => $name, ':id' => $row['id'], ':user' => $user->id)); #Change the folder name
					if(!$query->success) return $database->rollback() && false;
				}
			}

			return $database->commit() || $database->rollback() && false;
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

		public static function purge($account, System_1_0_0_User $user = null) #Empty trash of an account's
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT folder_trash FROM {$database->prefix}account WHERE id = :account AND user = :user");
			$query->run(array(':account' => $account, ':user' => $user->id)); #Get trash folder

			if(!$query->success) return false;
			$trash = $query->column();

			Mail_1_0_0_Item::update($trash); #Update the trash folder first

			$query = $database->prepare("SELECT id FROM {$database->prefix}mail WHERE user = :user AND folder = :trash");
			$query->run(array(':user' => $user->id, ':trash' => $trash)); #Select all of the mails in the trash folder

			if(!$query->success) return false;

			$list = array();
			foreach($query->all() as $row) $list[] = $row['id'];

			return Mail_1_0_0_Item::remove($list, false, $user); #Remove all the mails
		}

		public static function remove($folder, $remote = false, System_1_0_0_User $user = null) #Delete folders
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!is_array($folder)) return $log->param();
			if(!count($folder)) return true;

			foreach($folder as $id) if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$target = array(); #List of folders to delete
			$query = array();

			$query['base'] = $database->prepare("SELECT id, account, name, connector FROM {$database->prefix}folder WHERE id = :id AND user = :user");
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

				if(!Mail_1_0_0_Item::remove($delete, !$remote, $user)) return false; #Remove the mails in this folder

				$value = $target[] = ':i'.(++$count).'d'; #List the folder ID to delete
				$param[$value] = $base['id'];

				$query['child']->run(array(':user' => $user->id, ':account' => $base['account'], ':name' => $system->database_escape($base['name'].$base['connector']).'%')); #Find the child folders
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

					if(!Mail_1_0_0_Item::remove($delete, false, $user)) return false; #Remove the mails in this folder

					$value = $target[] = ':i'.(++$count).'d'; #List the folder ID to delete
					$param[$value] = $row['id'];

					if(!$remote || $type != 'imap') continue; #For deleting folders on IMAP server
					$box = '{'.$link['host'].'}'.mb_convert_encoding($row['name'], 'UTF7-IMAP', 'UTF-8');

					if(!imap_unsubscribe($link['connection'], $box)) return Mail_1_0_0_Account::error($link['host']); #Unsubscribe
					if(!imap_deletemailbox($link['connection'], $box)) return Mail_1_0_0_Account::error($link['host']); #Delete
				}

				if(!$remote || $type != 'imap') continue; #If attempting to delete remote folders on IMAP
				$box = '{'.$link['host'].'}'.mb_convert_encoding($base['name'], 'UTF7-IMAP', 'UTF-8');

				if(!imap_unsubscribe($link['connection'], $box)) return Mail_1_0_0_Account::error($link['host']); #Unsubscribe
				if(!imap_deletemailbox($link['connection'], $box)) return Mail_1_0_0_Account::error($link['host']); #Delete
			}

			if(!count($target)) return true;
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
			if(!preg_match('/\S/', $name) || strlen($name) > 255) return false; #TODO - Show better error, the max folder name length is?

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT account, id, name, connector, subscribed FROM {$database->prefix}folder WHERE id = :id AND user = :user");
			$query->run(array(':id' => $folder, ':user' => $user->id)); #Find the folder info

			if(!$query->success) return false;

			$current = $query->row();
			if($current['name'] == $name) return true; #If nothing to rename, quit

			$tree = $current['name'].$current['connector']; #Matching folder tree for child folders

			$query = $database->prepare("SELECT id, name, connector, subscribed FROM {$database->prefix}folder WHERE user = :user AND account = :account AND name LIKE :tree $database->escape");
			$query->run(array(':user' => $user->id, ':account' => $current['account'], ':tree' => $system->database_escape($tree).'%')); #Find child folders

			$children = $query->all();
			if(!$query->success) return false;

			if(strlen($current['connector'])) #If it has a folder delimiter
			{
				$partial = array_slice(explode($current['connector'], $current['name']), 0, -1);
				array_push($partial, $name);

				$target = implode($current['connector'], $partial); #New full folder path
			}
			else $target = $name;

			$query = $database->prepare("SELECT receive_type FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $current['account'], ':user' => $user->id)); #Pick the account type

			if(!$query->success) return false;

			if(Mail_1_0_0_Account::type($query->column()) == 'imap') #On IMAP rename on the mail server
			{
				$link = Mail_1_0_0_Account::connect($current['account'], '', $user); #Connect to the server
				if(!$link) return false;

				$from = '{'.$link['host'].'}'.mb_convert_encoding($current['name'], 'UTF7-IMAP', 'UTF-8');
				$to = '{'.$link['host'].'}'.mb_convert_encoding($target, 'UTF7-IMAP', 'UTF-8');

				if(!imap_renamemailbox($link['connection'], $from, $to) || $current['subscribed'] && !imap_subscribe($link['connection'], $to))
					return Mail_1_0_0_Account::error($link['host']); #Rename and restore the subscribe state if subscribed on server

				foreach($children as $row) #Update the subscribed status
				{
					if(!$row['subscribed']) continue;

					$name = preg_replace('/^'.preg_quote($tree, '/').'/', $target.$current['connector'], $row['name']);
					$name = '{'.$link['host'].'}'.mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8');

					#NOTE : Do not halt the process by this error
					if(!imap_subscribe($link['connection'], $name)) $log->dev(LOG_ERR, 'Cannot subscribe to a folder on the mail server : '.imap_last_error(), 'Check the error');
				}
			}

			if(!$database->begin()) return false; #Make renaming all atomic

			$query = $database->prepare("UPDATE {$database->prefix}folder SET name = :name WHERE id = :id AND user = :user");
			$query->run(array(':name' => $target, ':id' => $folder, ':user' => $user->id)); #Update the specified folder

			if(!$query->success) return $database->rollback() && false;

			foreach($children as $row) #Update the child folders in the database
			{
				$name = preg_replace('/^'.preg_quote($tree, '/').'/', $target.$current['connector'], $row['name']);

				$query->run(array(':name' => $name, ':id' => $row['id'], ':user' => $user->id)); #Change the folder name
				if(!$query->success) return $database->rollback() && false;
			}

			return $database->commit() || $database->rollback() && false;
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

			if(!strlen($name)) return false;

			$link = Mail_1_0_0_Account::connect($info['id'], '', $user); #Connect to the server
			$op = $mode ? imap_subscribe($link['connection'], '{'.$link['host']."}$name") : imap_unsubscribe($link['connection'], '{'.$link['host']."}$name");

			if(!$op) return Mail_1_0_0_Account::error($link['host']);

			$query = $database->prepare("UPDATE {$database->prefix}folder SET subscribed = :subscribed WHERE id = :id AND user = :user");
			$query->run(array(':subscribed' => $mode ? 1 : 0, ':id' => $folder, ':user' => $user->id));

			return $query->success; #NOTE : On failure, next folder sync will fix the inconsistency (Not running transaction during remote access)
		}

		public static function update($account, $suppress = false, System_1_0_0_User $user = null) #Update list of folders ($suppress will not consult remote POP3 server for unread counts)
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT * FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $account, ':user' => $user->id));

			$info = $query->row();
			if(!$query->success) return false;

			$query = $database->prepare("SELECT id, name, recent, subscribed FROM {$database->prefix}folder WHERE user = :user AND account = :account");
			$query->run(array(':user' => $user->id, ':account' => $account));

			if(!$query->success) return false;
			$local = $query->all();

			if(Mail_1_0_0_Account::type($info['receive_type']) == 'pop3') #If POP3, look through the list of mails to find unread mail counts
			{
				if(!$info['supported']) return true; #Do not try to count for new mails without UIDL support as every mail must be downloaded

				if(!$suppress) #If not suppressed, consult the POP3 server for amount of new mails
				{
					$conf = $system->app_conf(); #Configuration for this app
					$connection = Mail_1_0_0_Account::_special($info); #Get account connection parameters

					$pop3 = Mail_1_0_0_Item::_pop3($system, $connection['receiver']['host'], $connection['receiver']['port'], $connection['receiver']['secure'], $connection['receiver']['user'], $system->crypt_decrypt($connection['receiver']['pass'], $conf['key']), $user);
					if(!$pop3) return false;

					$list = $pop3->getListing(); #Get list of mails

					$query = $database->prepare("SELECT uid FROM {$database->prefix}loaded WHERE user = :user AND account = :account");
					$query->run(array(':user' => $user->id, ':account' => $account));

					if(!$query->success) return false;
					$exist = $remote = array();

					foreach($query->all() as $row) $exist[] = $row['uid']; #List of downloaded mails
					foreach($list as $row) $remote[] = $row['uidl']; #List of mails on the remote mail server

					$new = count(array_diff($remote, $exist)); #Amount of new mails yet to be downloaded
				}

				$query = array('count' => $database->prepare("SELECT count(id) FROM {$database->prefix}mail WHERE user = :user AND folder = :folder AND seen != :seen"));
				$query['update'] = $database->prepare("UPDATE {$database->prefix}folder SET count = :count, recent = :recent WHERE id = :id AND user = :user");

				foreach($local as $row)
				{
					$query['count']->run(array(':user' => $user->id, ':folder' => $row['id'], ':seen' => 1));
					if(!$query['count']->success) return false;

					$recent = $query['count']->column();

					if(strtolower($row['name']) == 'inbox')
					{
						$recent += $new; #Add the new mail count for inbox
						$count = $new;
					}
					else $count = 0; #No new mails will arrive other than in inbox

					$query['update']->run(array(':count' => $count, ':recent' => $recent, ':id' => $row['id'], ':user' => $user->id));
					if(!$query['update']->success) return false;
				}

				return true;
			}

			#For IMAP
			$link = Mail_1_0_0_Account::connect($account, '', $user); #Connect to the server

			$list = imap_getsubscribed($link['connection'], '{'.$link['host'].'}', '*'); #Get subscribed folder names
			if(!$list) return Mail_1_0_0_Account::error($link['host']);

			$subscribed = array();
			foreach($list as $info) $subscribed[] = $info->name; #List subscribed folder names

			$list = imap_getmailboxes($link['connection'], '{'.$link['host'].'}', '*'); #Get all folder names
			if(!$list) return Mail_1_0_0_Account::error($link['host']);

			foreach($local as $row) $exist[$row['name']] = $row; #Existing folders in the database

			$part = $link['info']['supported'] ? 'uid' : 'signature';
			$query = array('id' => $database->prepare("SELECT $part FROM {$database->prefix}mail WHERE user = :user AND folder = :folder"));

			$query['update'] = $database->prepare("UPDATE {$database->prefix}folder SET count = :count, recent = :recent, subscribed = :subscribed, connector = :connector, parent = :parent WHERE id = :id AND user = :user");
			$query['insert'] = $database->prepare("INSERT INTO {$database->prefix}folder (user, account, name, parent, recent, count, connector, subscribed) VALUES (:user, :account, :name, :parent, :recent, :count, :connector, :subscribed)");

			foreach($list as $folder) #TODO - It does not honor LATT_NOSELECT (Those folders not allowing to be selected for viewing)
			{
				if(imap_reopen($link['connection'], $folder->name) !== true) continue; #Change folder
				$name = preg_replace('/^{'.preg_quote($link['host'], '/').'}/', '', mb_convert_encoding($folder->name, 'UTF-8', 'UTF7-IMAP')); #Folder name

				$parent = $folder->attributes & LATT_NOINFERIORS == $folder->attributes ? 0 : 1; #Find if it can have child folders

				if($link['info']['supported']) #If UID is available
				{
					$identity = imap_search($link['connection'], 'UNDELETED', SE_UID); #All UID in the folder those are not deleted

					if(!is_array($identity) || !count($identity)) $count = 0; #If no mail on the mail server, set new mail count as 0
					elseif($exist[$name]) #If folder exists in the database
					{
						$query['id']->run(array(':user' => $user->id, ':folder' => $exist[$name]['id']));
						if(!$query['id']->success) return false;

						$loaded = array();
						foreach($query['id']->all() as $row) $loaded[] = $row[$part];

						$count = count(array_diff($identity, $loaded));
					}
					else $count = count($identity);
				}
				else
				{
					$identity = array();

					if($system->is_digit($amount = imap_num_msg($link['connection'])))
					{
						for($i = 0; $i < $amount; $i++)
						{
							$signature = $identity[] = imap_fetchheader($link['connection'], $i); #Signature of a message
							if(!$signature) return Mail_1_0_0_Account::error($link['host']);
						}
					}

					if(!count($identity)) $count = 0;
					elseif($exist[$name]['id'])
					{
						$query['id']->run(array(':user' => $user->id, ':folder' => $exist[$name]['id']));
						if(!$query['id']->success) return false;

						$loaded = array();
						foreach($query['id']->all() as $row) $loaded[] = $row[$part];

						$count = count(array_diff($identity, $loaded));
					}
					else $count = count($identity);
				}

				$recent = imap_status($link['connection'], $folder->name, SA_UNSEEN); #Check for new message numbers
				$recent = $recent->unseen ? $recent->unseen : 0;

				$read = in_array($folder->name, $subscribed) ? 1 : 0; #If subscribed or not

				if(!$exist[$name]) #If the folder does not exist in the database
				{
					$query['insert']->run(array(':user' => $user->id, ':account' => $account, ':name' => $name, ':parent' => $parent, ':recent' => $recent, ':count' => $count, ':connector' => $folder->delimiter, ':subscribed' => $read));
					if(!$query['insert']->success) return false; #Create the folder
				}
				else
				{
					$query['update']->run(array(':count' => $count, ':recent' => $recent, ':subscribed' => $read, ':connector' => $folder->delimiter, ':parent' => $parent, ':id' => $exist[$name]['id'], ':user' => $user->id));
					if(!$query['update']->success) return false;

					unset($exist[$name]);
				}
			}

			$remove = array();
			foreach($exist as $row) $remove[] = $row['id'];

			self::remove($remove, false, $user); #Remove the folder if not existing on the mail server
			return true;
		}
	}

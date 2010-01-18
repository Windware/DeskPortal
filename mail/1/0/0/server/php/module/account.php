<?php
	class Mail_1_0_0_Account
	{
		private static $_stream = array(); #Mail server connection cache

		public static function connect($account, $folder = null, System_1_0_0_User $user = null) #Connect to the mail server of an account
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account) || !$system->is_digit($folder) && $folder) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$name = $system->is_digit($folder) ? Mail_1_0_0_Folder::name($folder) : ''; #Convert it into textual name

			$query = $database->prepare("SELECT * FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $account, ':user' => $user->id));

			if(!$query->success) return false;

			$info = $query->row();
			$type = self::type($info['receive_type']);

			#Create connection parameters
			$host = "{$info['receive_host']}:{$info['receive_port']}";
			$secure = $info['receive_secure'] ? '/ssl' : '';

			$parameter = "{{$host}/$type/novalidate-cert$secure}";
			$conf = $system->app_conf('system', 'static');

			if(self::$_stream[$user->id][$account]) #If a connection already exists for the account
			{
				if(self::$_stream[$user->id][$account]['folder'] != $folder) #If the folder is different
				{
					$op = imap_reopen(self::$_stream[$user->id][$account]['connection'], $parameter.mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8')); #Open that folder
					if(!$op) return Mail_1_0_0_Account::error($host);

					self::$_stream[$user->id][$account]['folder'] = $folder;
				}

				return self::$_stream[$user->id][$account]; #Return the cached connection information
			}

			foreach(array(IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT) as $section)
			{
				$op = imap_timeout($section, $conf['net_timeout']); #Set the timeout value
				if(!$op) return Mail_1_0_0_Account::error($host);
			}

			$connection = imap_open($parameter.mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8'), $info['receive_user'], $info['receive_pass']);
			if(!$connection) return Mail_1_0_0_Account::error($host);

			return self::$_stream[$user->id][$account] = array('connection' => $connection, 'folder' => $folder, 'host' => $host, 'parameter' => $parameter, 'info' => $info, 'type' => $type);
		}

		public static function error($host) #Log IMAP errors
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			return $log->dev(LOG_ERR, "IMAP error on '$host' : ".imap_last_error(), 'Check the error');
		}

		public static function get($account = null, System_1_0_0_User $user = null) #Get account information
		{
			$system = new System_1_0_0(__FILE__);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$param = array(':user' => $user->id);

			if($system->is_digit($account))
			{
				$limit = " id = :id AND";
				$param[':id'] = $account;
			}

			$query = $database->prepare("SELECT * FROM {$database->prefix}account WHERE$limit user = :user");
			$query->run($param);

			if(!$query->success) return false;

			$result = $query->all();
			$list = '';

			foreach($result as $row)
			{
				$row['signature'] = str_replace("\n", '\\n', $row['signature']);
				$row['type'] = self::type($row['receive_type']);

				$list .= $system->xml_node('account', $row, null, explode(' ', 'user receive_pass send_pass'));
			}

			return $list;
		}

		public static function set($id, $param, System_1_0_0_User $user = null) #Save the account information
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id) || !is_array($param)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$param['user'] = $user->id;
			$data = $name = $variable = array();

			foreach($param as $key => $value)
			{
				if(preg_match('/\W/', $key) || !is_string($value)) continue;
				if(strlen($value) > 500) continue; #Avoid huge signatures

				if($id == 0)
				{
					$name[] = $key;
					$variable[] = ":$key";
				}
				else $name[] = "$key = :$key";

				$data[":$key"] = $value;
			}

			$name = implode(', ', $name);
			$database->begin();

			if($id == 0) #Insert new account data
			{
				$variable = implode(', ', $variable);

				$query = $database->prepare("INSERT INTO {$database->prefix}account ($name) VALUES ($variable)");
				$query->run($data);

				if(!$query->success) return false;
				$id = $database->id();
			}
			else #Edit current account data
			{
				$data[':id'] = $id;

				$query = $database->prepare("UPDATE {$database->prefix}account SET $name WHERE id = :id AND user = :user");
				$query->run($data);

				if(!$query->success) return false;
			}

			if(!self::connect($id, null, $user)) #Connect and check for availability
			{
				$database->rollback();
				return 2;
			}

			$database->commit();

			$query = $database->prepare("SELECT folder_inbox, folder_drafts, folder_sent, folder_trash FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id));

			if(!$query->success) return false;

			$account = $query->row();
			$query = array('select' => $database->prepare("SELECT id FROM {$database->prefix}folder WHERE user = :user AND account = :account AND name LIKE :name"));

			foreach(explode(' ', 'inbox drafts sent trash') as $name) #Look for special folders - TODO - Look for special folders that got created by several other clients (ex : Deleted Messages, Sent Messages)
			{
				if($system->is_digit($account["folder_$name"])) continue; #Ignore if already set

				$query['select']->run(array(':user' => $user->id, ':account' => $id, ':name' => $name)); #Look for special named folders
				if(!$query['select']->success) return false;

				if(!$system->is_digit($folder = $query['select']->column()))
				{
					$folder = Mail_1_0_0_Folder::create($id, null, $name == 'inbox' ? 'INBOX' : ucfirst($name), $user); #Create one if missing
					if(!$folder) continue;
				}

				$query['update'] = $database->prepare("UPDATE {$database->prefix}account SET folder_$name = :folder WHERE id = :id AND user = :user");
				$query['update']->run(array(':folder' => $folder, ':id' => $id, ':user' => $user->id)); #Remember the special folder ID

				if(!$query['update']->success) return false;
			}

			return true;
		}

		public static function type($type) #Return the connection type by account type
		{
			switch($type)
			{
				case 'pop3' : case 'hotmail' : return 'pop3'; break;

				case 'imap' : case 'gmail' : return 'imap'; break;

				default : return false; break;
			}
		}

		public static function update($account, $folder, $page, $order = 'sent', $reverse = true, System_1_0_0_User $user = null)
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account) || !$system->is_digit($folder) || !$system->is_digit($page) || !$system->is_text($order)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			if(Mail_1_0_0_Folder::update($account, $user) === false) return false;

			$storage = Mail_1_0_0_Folder::get($account, $user);
			if($storage === false) return false;

			if(Mail_1_0_0_Item::update($account, $folder, $user) === false) return false;

			$mail = Mail_1_0_0_Item::get($account, $folder, $page, $order, $reverse, false, null, $user);
			if($mail === false) return false;

			return $storage.$mail;
		}
	}
?>

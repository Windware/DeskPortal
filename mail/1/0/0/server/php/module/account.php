<?php

class Mail_1_0_0_Account
{
	private static $_stream = []; #Mail server connection cache

	public static function _special($param) #Get special account type parameters
	{
		switch($param['receive_type'])
		{
			case 'hotmail' :
				$receiver = ['host' => 'pop3.live.com', 'secure' => 1, 'port' => 995, 'user' => $param['address'], 'pass' => $param['receive_pass']];
				$sender = ['host' => 'smtp.live.com', 'secure' => 0, 'port' => 587, 'user' => $param['address'], 'pass' => $param['receive_pass']];
			break;

			case 'gmail' :
				$receiver = ['host' => 'imap.gmail.com', 'secure' => 1, 'port' => 993, 'user' => $param['address'], 'pass' => $param['receive_pass']];
				$sender = ['host' => 'smtp.gmail.com', 'secure' => 1, 'port' => 465, 'user' => $param['address'], 'pass' => $param['receive_pass']];
			break;

			case 'imap' :
			case 'pop3' :
				$receiver = ['host' => $param['receive_host'], 'secure' => $param['receive_secure'], 'port' => $param['receive_port'], 'user' => $param['receive_user'], 'pass' => $param['receive_pass']];
				$sender = ['host' => $param['send_host'], 'secure' => $param['send_secure'], 'port' => $param['send_port'], 'user' => $param['send_user'], 'pass' => $param['send_pass']];
			break;

			default :
				return [];
			break;
		}

		return ['receiver' => $receiver, 'sender' => $sender];
	}

	public static function connect($account, $folder = null, System_1_0_0_User $user = null) #Connect to the mail server of an account
	{
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		if(!$system->is_digit($account) || !$system->is_digit($folder) && $folder) return $log->param();

		if($user === null) $user = $system->user();
		if(!$user->valid) return false;

		$database = $system->database('user', __METHOD__, $user);
		if(!$database->success) return false;

		$name = $system->is_digit($folder) ? Mail_1_0_0_Folder::name($folder, $user) : ''; #Convert it into textual name

		$query = $database->prepare("SELECT * FROM {$database->prefix}account WHERE id = :id AND user = :user");
		if(!$query->run([':id' => $account, ':user' => $user->id])) return false;

		$conf = $system->app_conf();

		$info = $query->row();
		$format = self::_special($info); #Get parameters for special account types

		$info['receive_host'] = $format['receiver']['host'];
		$info['receive_port'] = $format['receiver']['port'];
		$info['receive_secure'] = $format['receiver']['secure'];
		$info['receive_user'] = $format['receiver']['user'];
		$info['receive_pass'] = $system->crypt_decrypt($format['receiver']['pass'], $conf['key']);

		$info['send_host'] = $format['sender']['host'];
		$info['send_port'] = $format['sender']['port'];
		$info['send_secure'] = $format['sender']['secure'];
		$info['send_user'] = $format['sender']['user'];
		$info['send_pass'] = $system->crypt_decrypt($format['sender']['pass'], $conf['key']);

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
				$op = imap_reopen(self::$_stream[$user->id][$account]['connection'], $parameter . mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8')); #Open that folder
				if(!$op) return Mail_1_0_0_Account::error($host);

				self::$_stream[$user->id][$account]['folder'] = $folder;
			}

			return self::$_stream[$user->id][$account]; #Return the cached connection information
		}

		foreach([IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT] as $section)
			if(!imap_timeout($section, $conf['net_timeout'])) return false; #Set the timeout value

		$connection = imap_open($parameter . mb_convert_encoding($name, 'UTF7-IMAP', 'UTF-8'), $info['receive_user'], $info['receive_pass']);
		if(!$connection) return Mail_1_0_0_Account::error($host);

		return self::$_stream[$user->id][$account] = ['connection' => $connection, 'folder' => $folder, 'host' => $host, 'parameter' => $parameter, 'info' => $info, 'type' => $type];
	}

	public static function error($host) #Log IMAP errors
	{
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		return $log->dev(LOG_ERR, "IMAP error on '$host' : " . imap_last_error(), 'Check the error');
	}

	public static function get($account = null, System_1_0_0_User $user = null) #Get account information
	{
		$system = new System_1_0_0(__FILE__);

		if($user === null) $user = $system->user();
		if(!$user->valid) return false;

		$database = $system->database('user', __METHOD__, $user);
		if(!$database->success) return false;

		$param = [':user' => $user->id];

		if($system->is_digit($account))
		{
			$limit = " id = :id AND";
			$param[':id'] = $account;
		}

		$query = $database->prepare("SELECT * FROM {$database->prefix}account WHERE$limit user = :user ORDER BY LOWER(description)");
		if(!$query->run($param)) return false;

		$list = [];

		foreach($query->all() as $row)
		{
			$row['signature'] = str_replace("\n", '\\n', $row['signature']);
			$row['type'] = self::type($row['receive_type']);

			unset($row['receive_pass'], $row['send_pass']); #Remove user passwords
			$list[] = $row;
		}

		return $list;
	}

	public static function remove($id, System_1_0_0_User $user = null) #Remove an account
	{
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		if(!$system->is_digit($id)) return $log->param();

		if($user === null) $user = $system->user();
		if(!$user->valid) return false;

		$database = $system->database('user', __METHOD__, $user);
		if(!$database->success) return false;

		if(!$database->begin()) return false;

		$query = $database->prepare("DELETE FROM {$database->prefix}account WHERE id = :id AND user = :user");
		if(!$query->run([':id' => $id, ':user' => $user->id])) return $database->rollback() && false; #Remove the account

		$query = $database->prepare("DELETE FROM {$database->prefix}loaded WHERE user = :user AND account = :account");
		if(!$query->run([':user' => $user->id, ':account' => $id])) return $database->rollback() && false; #Remove the POP3 download history

		$query = $database->prepare("SELECT id FROM {$database->prefix}folder WHERE user = :user AND account = :account");
		if(!$query->run([':user' => $user->id, ':account' => $id])) return $database->rollback() && false; #Find the folders

		$folder = []; #Folder list
		foreach($query->all() as $row) $folder[] = $row['id'];

		$query = $database->prepare("DELETE FROM {$database->prefix}folder WHERE user = :user AND account = :account");
		if(!$query->run([':user' => $user->id, ':account' => $id])) return $database->rollback() && false; #Remove the folders

		$mail = []; #Mail list

		for($i = 0; $i < count($folder); $i += $database->limit)
		{
			$param = [':user' => $user->id];
			$target = [];

			$index = 0;

			foreach(array_slice($folder, $i, $database->limit) as $id)
			{
				$value = $target[] = ":i{$index}d";
				$param[$value] = $id;

				$index++;
			}

			if(!count($target)) continue;
			$target = implode(', ', $target);

			$query = $database->prepare("SELECT id FROM {$database->prefix}mail WHERE user = :user AND folder IN ($target)");
			if(!$query->run($param)) return $database->rollback() && false; #Find the mails

			foreach($query->all() as $row) $mail[] = $row['id'];

			$query = $database->prepare("DELETE FROM {$database->prefix}mail WHERE user = :user AND folder IN ($target)");
			if(!$query->run($param)) return $database->rollback() && false; #Delete the mails
		}

		for($i = 0; $i < count($mail); $i += $database->limit)
		{
			$param = $target = [];
			$index = 0;

			foreach(array_slice($mail, $i, $database->limit) as $id)
			{
				$value = $target[] = ":i{$index}d";
				$param[$value] = $id;

				$index++;
			}

			if(!count($target)) continue;
			$target = implode(', ', $target);

			foreach(explode(' ', 'from to cc attachment reference') as $section)
			{
				$query = $database->prepare("DELETE FROM {$database->prefix}$section WHERE mail IN ($target)");
				if(!$query->run($param)) return $database->rollback() && false; #Delete mail related data
			}

			$query = $database->prepare("DELETE FROM {$database->prefix}reference WHERE reference IN ($target)");
			if(!$query->run($param)) return $database->rollback() && false; #Delete mail referer
		}

		return $database->commit() || $database->rollback() && false;
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

		foreach([IMAP_OPENTIMEOUT, IMAP_READTIMEOUT, IMAP_WRITETIMEOUT, IMAP_CLOSETIMEOUT] as $section)
			if(!imap_timeout($section, $system->app_conf('system', 'static', 'net_timeout'))) return false; #Set the timeout value

		$conf = $system->app_conf();

		if($id != 0 && (!strlen($param['receive_pass']) || !strlen($param['send_pass']))) #If either of the password field is left blank
		{
			$query = $database->prepare("SELECT receive_pass, send_pass FROM {$database->prefix}account WHERE id = :id AND user = :user");
			if(!$query->run([':id' => $id, ':user' => $user->id])) return false; #Recover passwords from database

			$password = $query->row();

			if(!strlen($param['receive_pass'])) $param['receive_pass'] = $system->crypt_decrypt($password['receive_pass'], $conf['key']);
			if(!strlen($param['send_pass'])) $param['send_pass'] = $system->crypt_decrypt($password['send_pass'], $conf['key']);
		}

		$info = self::_special($param); #Get parameters for special account types
		$type = self::type($param['receive_type']);

		if($type == 'imap') #For IMAP, connect directly and check for capability of UID
		{
			$secure = $info['receiver']['secure'] ? '/ssl' : '';
			$connection = imap_open("{{$info['receiver']['host']}:{$info['receiver']['port']}/$type/novalidate-cert$secure}", $info['receiver']['user'], $info['receiver']['pass']);

			if(!$connection) return 2; #Check for authentication (TODO : Could manually authenticate below just to avoid yet another connection)
			imap_close($connection);

			$timeout = $system->app_conf('system', 'static', 'net_timeout');
			ini_set('default_socket_timeout', $timeout);

			$secure = $info['receiver']['secure'] ? 'ssl://' : '';

			$connection = fsockopen($secure . $info['receiver']['host'], $info['receiver']['port']); #Open a raw connection to retrieve the mail server capability
			if(!$connection) return 2;

			stream_set_timeout($connection, $timeout);
			if(fgets($connection) === false) return 2; #Receive the salute

			if(!fwrite($connection, "1 CAPABILITY\r\n")) return 2; #Request for its capability
			$response = fgets($connection); #Capability response

			fclose($connection);
			if(!strlen($response)) return 2;

			$param['supported'] = !!preg_match('/ imap4/i', $response) ? '1' : '0'; #If IMAP version 4, then it supports UID (NOTE : Keep as string to pass 'is_string' test)

		}
		elseif($type == 'pop3') #NOTE : For POP3, check for UIDL capability as well as authentication
		{
			if(!$system->file_load('Auth/SASL.php') || !$system->file_load('Net/POP3.php')) return false;

			$secure = $info['receiver']['secure'] ? 'ssl://' : ''; #Specify SSL connection if configured so
			$pop3 =& new Net_POP3();

			$connect = $pop3->connect($secure . $info['receiver']['host'], $info['receiver']['port']); #Connect
			if($connect === true) $connect = $pop3->login($info['receiver']['user'], $info['receiver']['pass']); #Login

			if($connect !== true) return 2;
			$param['supported'] = $pop3->_cmdUidl() !== false ? '1' : '0'; #See if UIDL command succeeded (NOTE : Keep as string to pass 'is_string' test)
		}

		if(!$system->file_load('Net/SMTP.php')) return false; #Load the PEAR package for SMTP connection

		$secure = $info['sender']['secure'] ? 'ssl://' : '';
		$smtp = new Net_SMTP($secure . $info['sender']['host'], $info['sender']['port'], $info['sender']['host']);

		$connect = $smtp->connect(); #Connect and check for send server availability
		if($connect === true && strlen($info['sender']['user']) || strlen($info['sender']['pass'])) $connect = $smtp->auth($info['sender']['user'], $info['sender']['pass']);

		$smtp->disconnect();
		if($connect !== true) return 3; #Connect and check for availability on send mail server

		$param['user'] = $user->id;
		$data = $name = $variable = [];

		foreach($param as $key => $value)
		{
			if(preg_match('/\W/', $key) || !is_string($value)) continue;

			if($id == 0)
			{
				$name[] = $key;
				$variable[] = ":$key";
			}
			else $name[] = "$key = :$key";

			$data[":$key"] = $value;
		}

		$name = implode(', ', $name);
		if(!$database->begin()) return false;

		$query = $database->prepare("SELECT count(id) FROM {$database->prefix}account WHERE id != :id AND user = :user AND base = :base");

		if(!$query->run([':id' => $id, ':user' => $user->id, ':base' => 1])) return $database->rollback() && false; #Find any default accounts
		if(!$query->column()) $data[':base'] = 1; #If no other default accounts are set, force it to be default

		if($data[':base']) #If a new default account is set
		{
			$query = $database->prepare("UPDATE {$database->prefix}account SET base = :base WHERE user = :user");
			if(!$query->run([':base' => 0, ':user' => $user->id])) return $database->rollback() && false; #Reset the previous default account
		}

		#Encrypt the passwords when storing
		$data[':receive_pass'] = strlen($data[':receive_pass']) ? $system->crypt_encrypt($data[':receive_pass'], $conf['key']) : null;
		$data[':send_pass'] = strlen($data[':send_pass']) ? $system->crypt_encrypt($data[':send_pass'], $conf['key']) : null;

		if($id == 0) #Insert new account data
		{
			$variable = implode(', ', $variable);

			$query = $database->prepare("INSERT INTO {$database->prefix}account ($name) VALUES ($variable)");
			if(!$query->run($data)) return $database->rollback() && false;

			$id = $database->id();
		}
		else #Edit current account data
		{
			$data[':id'] = $id;

			$query = $database->prepare("UPDATE {$database->prefix}account SET $name WHERE id = :id AND user = :user");
			if(!$query->run($data)) return $database->rollback() && false;
		}

		if(!$database->commit()) return $database->rollback() && false;
		Mail_1_0_0_Folder::update($id, $user); #Update the folder listing on an account

		$query = $database->prepare("SELECT folder_inbox, folder_drafts, folder_sent, folder_trash FROM {$database->prefix}account WHERE id = :id AND user = :user");
		if(!$query->run([':id' => $id, ':user' => $user->id])) return false;

		$account = $query->row();

		$query = ['select' => $database->prepare("SELECT id FROM {$database->prefix}folder WHERE user = :user AND account = :account AND name LIKE :name AND subscribed = :subscribed")];
		$query['subscribe'] = $database->prepare("UPDATE {$database->prefix}folder SET subscribed = :subscribed WHERE id = :id AND user = :user");

		foreach(explode(' ', 'inbox drafts sent trash') as $name) #Look for special folders
		{
			if($system->is_digit($account["folder_$name"])) continue; #Ignore if already set
			$candidate = []; #List of folders to check for existing as special folders

			switch($name) #Make a guess for any existing special mail boxes
			{
				case 'inbox' :
					$candidate = ['INBOX'];
				break; #Use a global inbox name

				case 'drafts' :
					switch($param['receive_type'])
					{
						case 'gmail' :
							$candidate = ['[Gmail]/Drafts'];
						break;
					}
				break;

				case 'sent' :
					switch($param['receive_type'])
					{
						case 'gmail' :
							$candidate = ['[Gmail]/Sent Mail'];
						break;

						default :
							$candidate = ['Sent Messages'];
						break; #Apple Mail style
					}
				break;

				case 'trash' :
					switch($param['receive_type'])
					{
						case 'gmail' :
							$candidate = ['[Gmail]/Trash'];
						break;

						default :
							$candidate = ['Deleted Messages'];
						break; #Apple Mail style
					}
				break;
			}

			$candidate[] = ucfirst($name); #Use a generic name
			$folder = null;

			foreach($candidate as $store)
			{
				if(!$query['select']->run([':user' => $user->id, ':account' => $id, ':name' => $store, ':subscribed' => 1])) return false; #Look for special named folders
				if($folder = $query['select']->column()) break;
			}

			if(!$folder) #If the folder does not exist
			{
				$folder = Mail_1_0_0_Folder::create($id, null, $store, $user); #Create a generic name folder
				if(!$folder) continue; #Ignore setting this special folder if it cannot be created
			}

			$query['update'] = $database->prepare("UPDATE {$database->prefix}account SET folder_$name = :folder WHERE id = :id AND user = :user");
			if(!$query['update']->run([':folder' => $folder, ':id' => $id, ':user' => $user->id])) return false; #Remember the special folder ID

			if(!$query['subscribe']->run([':subscribed' => 1, ':id' => $folder, ':user' => $user->id])) return false; #Make sure the folder is subscribed
		}

		return true;
	}

	public static function type($type) #Return the connection type by account type
	{
		switch($type)
		{
			case 'pop3' :
			case 'hotmail' :
				return 'pop3';
			break;

			case 'imap' :
			case 'gmail' :
				return 'imap';
			break;

			default :
				return false;
			break;
		}
	}
}

?>

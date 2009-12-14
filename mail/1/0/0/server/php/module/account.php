<?php
	class Mail_1_0_0_Account
	{
		public static function connect($account, $folder, System_1_0_0_User $user = null) #Connect to the mail server of an account
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account)) return $log->param();
			if(!is_string($folder)) $folder = '';

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT * FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $account, ':user' => $user->id));

			if(!$query->success) return false;
			$info = $query->row();

			#Create connection parameters
			$host = "{$info['receive_host']}:{$info['receive_port']}";
			$secure = $info['receive_secure'] ? '/ssl' : '';

			$parameter = "{{$host}/{$info['receive_type']}/novalidate-cert$secure}";
			$connection = imap_open($parameter.imap_utf7_encode($folder), $info['receive_user'], $info['receive_pass']);

			if(!$connection) $log->dev(LOG_ERR, 'Failed opening connection to a mail server', 'Check user configuration');
			return array($connection, $host, $parameter);
		}

		public static function get(System_1_0_0_User $user = null) #Get list of accounts
		{
			$system = new System_1_0_0(__FILE__);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT * FROM {$database->prefix}account WHERE user = :user");
			$query->run(array(':user' => $user->id));

			if(!$query->success) return false;
			$result = $query->all();

			foreach($result as $row)
			{
				$exclude = explode(' ', 'user receive_pass send_pass');

				$signature = str_replace("\n", '\\n', $row['signature']);
				$account .= $system->xml_node('account', $row, null, $exclude);
			}

			return $account;
		}

		public static function save($id, $param, System_1_0_0_User $user = null) #Save the account information
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

			if($id == 0)
			{
				$variable = implode(', ', $variable);

				$query = $database->prepare("INSERT INTO {$database->prefix}account ($name) VALUES ($variable)");
				$query->run($data);
			}
			else
			{
				$data[':id'] = $id;

				$query = $database->prepare("UPDATE {$database->prefix}account SET $name WHERE id = :id AND user = :user");
				$query->run($data);
			}

			return $query->success;
		}
	}
?>

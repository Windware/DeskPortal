<?php

class System_1_0_0_User
{
	private $_system;

	private static $_identity = 'mail'; //Unique identity field for a user (System_1_0_0::is_identity and $system.is.identity should also align)

	public $id, $identity, $system, $ticket, $valid;

	//NOTE : Manual for 'login' app needs changed if this value changes
	//Must be in sync with the value set in start.js 'this.user.pref.logout' value for 'system_static'
	public static $_last = 30; //Default minutes for auto logout mechanism

	public function __construct(&$system, $id = null) //Get the user info (If no user is specified, gets the logged in user's)
	{
		$this->valid = false; //Invalidate the user object until configured

		$this->_system = $system;
		$log = $system->log(__METHOD__);

		if($id === null) //If not specified, get the logged in user
		{
			if(!$system->is_ticket(@$_COOKIE['ticket']) || !$system->is_identity(@$_COOKIE['identity'])) //And if they look wrong
			{
				if(@$_COOKIE['ticket'] !== '' || @$_COOKIE['identity'] !== '') //If cookies are sent, log for improper cookies
					$log->dev(LOG_NOTICE, 'Client has not sent proper cookie for user', 'Check client cookies for valid values');

				return;
			}

			$target = 'currently logged in user'; //Description to be put in the log
		}
		else
		{
			if(!$system->is_digit($id)) return $log->param();
			$target = "user ID '$id'"; //If user name/ID is specified
		}

		$log->dev(LOG_INFO, "Checking for presence of $target in the database");

		$database = $system->database('system', __METHOD__, null, 'system', 'static'); //Open database
		if(!$database->success) return false;

		$conf = $system->app_conf('system', 'static');
		$identity = self::$_identity;

		if($id === null) //For logged in user
		{
			if($conf['system_demo']) //For demo mode
			{
				$query = $database->prepare("SELECT id, invalid FROM {$database->prefix}user WHERE $identity = :identity");
				$parameters = [':identity' => $_COOKIE['identity']]; //Only check the user name to allow multiple client accesses
			}
			else
			{
				//Query to be used
				$query = $database->prepare("SELECT user.id, user.$identity, user.invalid FROM {$database->prefix}user AS user, {$database->prefix}session AS session WHERE session.ticket = :ticket AND session.ip = :ip AND session.user = user.id AND user.$identity = :identity");
				$parameters = [':ticket' => $_COOKIE['ticket'], ':ip' => $_SERVER['REMOTE_ADDR'], ':identity' => $_COOKIE['identity']];
			}

			//Specify data for logging
			$request = "'{$_COOKIE['identity']}'";
			$solution = 'Check for client cookie or user\'s presence';
		}
		else //If user ID is specified
		{
			//Query to be used
			$query = $database->prepare("SELECT id, $identity, invalid FROM {$database->prefix}user WHERE id = :id");
			$parameters = [':id' => $id];

			//Specify data for logging
			$request = "ID '$id'";
			$solution = 'Check for user\'s presence';
		}

		$query->run($parameters); //Run the query

		if(!$query->success) //If user is not found, quit
		{
			$log->dev(LOG_NOTICE, "User $request cannot be found", $solution);
			return $this->id = null;
		}

		$row = $query->row(); //Get the result

		//Set user parameters
		$this->id = $row['id'];
		$this->identity = $row[$identity];
		$this->ticket = $_COOKIE['ticket'];

		//Consider the user valid if it has a proper ID and not flagged invalid
		if($system->is_digit($this->id) && $row['invalid'] != 1) $this->valid = true;
	}

	public function conf($table, $list = null) //Gets the user's preference list
	{
		$system = $this->_system;
		$log = $system->log(__METHOD__);

		if(!$this->valid || !$system->is_digit($this->id)) return false;
		if(!in_array($table, explode(' ', 'conf used window'))) return $log->param();

		$user = $system->user(); //Load the user
		if(!$user->valid) return false;

		$database = $system->database('user', __METHOD__, null, 'system', 'static');
		if(!$database->success) return false;

		$log->dev(LOG_INFO, "Getting user configuration for user ID '$this->id'");
		$values = [':id' => $this->id]; //Parameter to be passed to the query

		if(($table === 'conf' || $table === 'window') && is_array($list)) //For application table lookup
		{
			$limiter = []; //List of application list
			$parameter = []; //List of query parameters

			foreach($list as $index => $app) //Compile the query from the application names
			{
				$limiter[] = "app LIKE :app{$index}_index $database->escape";
				$parameter[":app{$index}_index"] = $system->database_escape($app) . '%';
			}

			//Set parameter to filter out the application names
			$limiter = count($limiter) > 0 ? ' AND (' . implode(' OR ', $limiter) . ')' : '';
			$values = array_merge($values, $parameter);
		}

		$query = $database->prepare("SELECT * FROM {$database->prefix}$table WHERE user = :id$limiter");
		$query->run($values); //Select the user's general configuration

		if($query->success) return $query->all(); //Return the configuration list

		$problem = "Cannot fetch user preferences for user ID '$this->id'";
		return $log->dev(LOG_ERR, $problem, "Check user database for '{$database->prefix}$table' table");
	}

	public static function create(&$system, $identity, $password) //Create a new user
	{
		$log = $system->log(__METHOD__);
		if(!$system->is_identity($identity) || !is_string($password)) return $log->param();

		$user = $system->user_find($identity); //If the name already exists, don't try
		if($user->valid) return $log->dev(LOG_NOTICE, "User '$identity' already exists", 'Choose another name', $user->id);

		$conf = $system->app_conf('system', 'static');

		if(!in_array($conf['user_hash'], hash_algos())) //Make sure the server is capable of using the hash algorithm
		{
			$solution = 'Check system configuration \'user_hash\' value and if PHP supports the algorithm';
			return $log->system(LOG_ERR, 'Hash algorithm is invalid', $solution);
		}

		$database = $system->database('system', __METHOD__, null, 'system', 'static'); //Open database
		if(!$database->success) return false;

		$log->dev(LOG_INFO, "Creating a new user '$identity'");

		$query = $database->prepare("INSERT INTO {$database->prefix}user (" . self::$_identity . ', password, joined) VALUES (:identity, :password, :joined)');
		$query->run([':identity' => $identity, ':password' => hash($conf['user_hash'], $password), ':joined' => gmdate('Y-m-d')]);

		if(!$query->success) return false;
		$id = $database->id(); //Get the created user's ID

		if(!$system->is_digit($id))
			return $log->system(LOG_ERR, 'Creating a new user failed : ' . $error->getMessage(), 'Check database and error');

		return $id; //Return the created user's ID
	}

	public static function find(&$system, $identity) //Find user ID from name
	{
		static $_user; //Store the user id once calculated
		$log = $system->log(__METHOD__);

		if($system->is_digit($_user[$identity])) return $_user[$identity]; //If the user ID is calculated, return it instead

		$database = $system->database('system', __METHOD__, null, 'system', 'static'); //Open database
		if(!$database->success) return false;

		$log->dev(LOG_INFO, "Finding user '$identity'");

		$query = $database->prepare("SELECT id FROM {$database->prefix}user WHERE " . self::$_identity . ' = :identity'); //Find out the user ID
		$query->run([':identity' => $identity]);

		$id = $query->column();

		if(!$system->is_digit($id)) //If user ID cannot be found, quit
			return $log->system(LOG_NOTICE, "User '$identity' not found in system", 'Check for name to check');

		return new self($system, $id); //Initialize and return the own user object
	}

	public static function get(&$system, $identity) //Find user ID from name
	{
		static $_user; //Store the user id once calculated
		$log = $system->log(__METHOD__);

		if($system->is_digit($_user[$identity])) return $_user[$identity]; //If the user ID is calculated, return it instead

		$database = $system->database('system', __METHOD__, null, 'system', 'static'); //Open database
		if(!$database->success) return false;

		$log->dev(LOG_INFO, "Finding user '$identity'");

		$query = $database->prepare("SELECT id FROM {$database->prefix}user WHERE " . self::$_identity . ' = :identity'); //Find out the user ID
		$query->run([':identity' => $identity]);

		$id = $query->column();

		if(!$system->is_digit($id)) //If user ID cannot be found, quit
			return $log->system(LOG_NOTICE, "User '$identity' not found in system", 'Check for name to check');

		return new self($system, $id); //Initialize and return the own user object
	}

	public function refresh() //Update the user ticket information
	{
		$system = $this->_system;
		$log = $system->log(__METHOD__);

		if(!$this->valid) return -1; //If the user is invalid, quit

		foreach($this->conf('conf', ['system_static']) as $conf) //Set a very long expire date when auto logout is turned off
			if($conf['name'] === 'logout' && $system->is_digit($conf['value'])) $period = $conf['value'] == 0 ? 365 * 24 * 60 : $conf['value'];

		if(!$period) $period = self::$_last; //If never set, use the default auto logout value
		$period = time() + $period * 60; //Turn into expiration time

		return $system->cookie_set('ticket', $this->ticket, $period) && $system->cookie_set('identity', $this->identity, $period); //Resend the same cookies with new expire time
	}

	public function save($section, $data, $id = null) //Update user configuration values
	{
		$system = $this->_system;
		$log = $system->log(__METHOD__);

		if(!$system->is_text($section) || !is_array($data) || $id !== null && !$system->is_text($id)) return $log->param();
		if(!$this->valid || count($data) === 0) return false;

		$database = $system->database('user', __METHOD__, $this, 'system', 'static'); //Load the database
		if(!$database->success) return false;

		$report = [];

		switch($section)
		{
			case 'conf' :
			case 'window' : //Update the window information
				if(!$database->begin()) return false;
				$query = $database->prepare("REPLACE INTO {$database->prefix}$section (user, app, name, value) VALUES (:user, :app, :name, :value)");

				foreach($data as $key => $value)
				{
					if(!$system->is_text($key)) continue;

					$query->run([':user' => $this->id, ':app' => $id, ':name' => $key, ':value' => $value]);
					if(!$query->success) return $database->rollback() && false;
				}

				return $database->commit() || $database->rollback() && false;
			break;

			case 'used' : //Update the loaded state
				$app = $system->app_info($id);
				$query = $database->prepare("REPLACE INTO {$database->prefix}used (user, app, version, loaded) VALUES (:user, :app, :version, :loaded)");

				$query->run([':user' => $this->id, ':app' => $app['name'], ':version' => $app['version'], ':loaded' => $data['loaded']]);
				return $query->success;
			break;

			case 'version' : //Update used version (NOTE : Not using UPDATE statement in case the row does not exist)
				if(!$system->is_app($data['name'], $data['version'])) return false;

				$query = $database->prepare("SELECT loaded FROM {$database->prefix}used WHERE user = :user AND app = :app");
				$query->run([':user' => $user->id, ':app' => $data['name']]);

				$loaded = $query->column(); //Get the current loaded state if any
				if($loaded === false) $loaded = 0;

				$query = $database->prepare("REPLACE INTO {$database->prefix}used (user, app, version, loaded) VALUES (:user, :app, :version, :loaded)");
				$query->run([':user' => $this->id, ':app' => $data['name'], ':version' => $data['version'], ':loaded' => $loaded]);

				return $query->success;
			break;

			default :
				return false;
			break;
		}
	}

	public function subscribed($app, $public = true) //Find out if the user is subscribed to the given application
	{
		$system = $this->_system;
		$log = $system->log(__METHOD__);

		if(!$this->valid) return false;
		if(!$system->is_app($app)) return $log->param();

		if($public && $system->app_public($app)) return true; //Check for public availability too if specified

		//If not logged in or system database does not work, there's nothing to check
		$database = $system->database('system', __METHOD__, null, 'system', 'static');
		if(!$database->success) return false;

		$log->dev(LOG_INFO, "Finding out if '$app' is subscribed by user ID '$this->id'");

		//Find out from the system database if the application is subscribed by the logged in user.
		$query = $database->prepare("SELECT user FROM {$database->prefix}subscription WHERE user = :user AND app = :app AND invalid = :invalid");
		$query->run([':user' => $this->id, ':app' => $app, ':invalid' => 0]); //Get the number of returned rows

		return !!count($query->all()); //If any, return true
	}

	public function xml($section, $app = null, $language = null) //Return list of user information in XML
	{
		$system = $this->_system;
		$log = $system->log(__METHOD__);

		if(!$this->valid) return false;

		switch($section)
		{
			case 'used' :
				$database = $system->database('system', __METHOD__, null, 'system', 'static');
				if(!$database->success) return false;

				$query = $database->prepare("SELECT app FROM {$database->prefix}subscription WHERE user = :user AND invalid = :invalid");
				$query->run([':user' => $this->id, ':invalid' => 0]);

				//Load the user specific configuration of apps
				foreach($this->conf('used') as $row) $used[$row['app']] = ['version' => $row['version'], 'loaded' => $row['loaded']];

				$list = []; //Available app list
				foreach($query->all() as $row) $list[] = $row['app'];

				foreach(array_merge($system->app_conf('system', 'static', 'app_public'), $list) as $name) //Add public app list
				{
					$info = []; //App info

					$info['app'] = $name;
					$info['version'] = $used[$name]['version'];

					if(!$info['version']) $info['version'] = '1_0_0'; //FIXME - If no version is specified, give a newest version and save into db
					$file = $system->language_file("{$name}_{$info['version']}", 'title.txt', $language);

					$info['title'] = trim($system->file_read($file, LOG_NOTICE)); //The actual language specific title
					if(!$info['title']) continue; //Drop the entry that has no title given (App might not exist)

					$info['loaded'] = $used[$name]['loaded'];

					$list = $version = []; //List of existing versions
					$available = ''; //Available versions in XML

					foreach(glob("$name/*/*/*/") as $revision) //Get all version numbers
						if(preg_match('|^.+?/(\d+)/(\d+)/\d+/$|', $revision, $matches)) $version[$matches[1]][$matches[2]]++; //Add amount of revisions

					foreach($version as $major => $minor) //For all available versions
					{
						$list['major'] = $major;

						foreach($minor as $number => $revisions)
						{
							$list['minor'] = $number;
							$list['revisions'] = $revisions;

							$available .= $system->xml_node('version', $list);
						}
					}

					$xml .= $system->xml_node($section, $info, $available, ['user']); //Create XML from the values
				}
			break;

			case 'conf' :
			case 'window' :
				$conf = $this->conf($section, $app);
				if(is_array($conf)) foreach($conf as $row) $xml .= $system->xml_node($section, $row, null, ['user']); //Create XML from the values
			break;

			default :
				return $log->param();
			break;
		}

		return $xml;
	}
}

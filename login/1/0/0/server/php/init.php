<?php

class Login_1_0_0
{
	protected static $_mode = ['success' => 0, 'failure' => 1, 'error' => 2]; //Define the return code

	protected static $_version = 1; //Database version to use

	//Attempt to login, record the login and then issue a ticket back via cookie if successful
	public static function attempt($identity, $pass, $keep) //Return codes are (0 : sucess, 1 : fail, 2 : error)
	{
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		$identity = strtolower($identity);

		if($system->is_ticket($_COOKIE['ticket'])) //If the client already has a ticket, abort but report success
		{
			$problem = "User '$identity' tried to authenticate again while already authenticated";
			return $log->user(LOG_NOTICE, $problem, 'Do not authenticate again while logged in', self::$_mode['success']);
		}

		$response = $system->is_digit($id = self::auth($identity, $pass)); //Make login authentication
		$situation = $response ? 'succeeded' : 'failed'; //Set state

		$log->system(LOG_NOTICE, "Authentication sequence $situation for user '$identity' from '{$_SERVER['REMOTE_ADDR']}'");

		//Not logging failures to the user's database for now even if the table has a column to indicate success or failure
		if(!$response) return self::$_mode['failure']; //If failed to login, quit

		if(headers_sent()) //If headers are sent, give up
			return $log->system(LOG_ERR, 'Cannot send out cookies', 'Do not send contents before setting cookie headers', self::$_mode['error']);

		$time = $system->date_datetime(); //Grab current time
		$ticket = md5(mt_rand()); //Create a ticket with a random string

		$conf = $system->app_conf('system', 'static');

		if(!$conf['system_demo']) //If not under demo mode
		{
			$database = $system->database('system', __METHOD__, null, 'system', 'static'); //Open the database
			if(!$database->success) return self::$_mode['error'];

			//Record the login session
			$query = $database->prepare("REPLACE INTO {$database->prefix}session (user, ticket, ip, time) VALUES (:user, :ticket, :ip, :time)");
			$query->run([':user' => $id, ':ticket' => $ticket, ':ip' => $_SERVER['REMOTE_ADDR'], ':time' => $time]);

			if(!$query->success) return self::$_mode['error'];

			//Pretend the user has sent the right cookie to open the user's database
			$_COOKIE['identity'] = $identity;
			$_COOKIE['ticket'] = $ticket;

			$user = $system->user(null, true); //Find the logged in user
			if(!$user->valid) return self::$_mode['error']; //If the user cannot be retrieved, quit

			$query = $database->prepare("SELECT logged FROM {$database->prefix}user WHERE id = :id");
			$query->run([':id' => $user->id]);

			if(!$query->success) return self::$_mode['error'];
			$count = $query->column(); //The current mode for the user

			$query = $database->prepare("UPDATE {$database->prefix}user SET logged = :logged WHERE id = :id");
			$query->run([':id' => $user->id, ':logged' => $count + 1]); //Increment the login count

			if(!$query->success) return self::$_mode['error'];

			if($count == 0) //If it is the first time logging in
			{
				if(!$database->begin()) return self::$_mode['error']; //Make sure results will not be partial
				$query = $database->prepare("REPLACE INTO {$database->prefix}subscription (user, app) VALUES (:id, :app)");

				foreach($conf['app_subscribed'] as $app) //For all of the initial subscribed applications
				{
					if(!$system->is_app($app))
					{
						$problem = 'Invalid application name specified for \'app_subscribed\' system configuration list';
						$log->system(LOG_ERR, $problem, 'Check the system configuration parameter');

						continue; //Leave out an invalid application
					}

					$query->run([':id' => $user->id, ':app' => $app]); //Update the version information
					if(!$query->success) return $database->rollback() && self::$_mode['error'];
				}

				if(!$database->commit()) return $database->rollback() && self::$_mode['error']; //Commit the change

				$database = $system->database('user', __METHOD__, null, 'system', 'static'); //Open the user's database
				if(!$database->begin()) return self::$_mode['error']; //Start the transaction for the next batch of queries

				$find = $database->prepare("SELECT COUNT(user) FROM {$database->prefix}used WHERE user = :user AND app = :app");
				$insert = $database->prepare("INSERT INTO {$database->prefix}used (user, app, version, loaded) VALUES (:user, :app, :version, :loaded)");

				foreach(array_merge($conf['app_subscribed'], $conf['app_public']) as $app)
				{
					$find->run([':user' => $user->id, ':app' => $app]);
					if(!$find->success) return $database->rollback() && self::$_mode['error'];

					if($find->column() != 0) continue; //If the entry exists for some reason, leave it alone
					$loaded = in_array($app, $conf['app_initial']) ? 1 : 0; //See if it should be loaded

					$insert->run([':user' => $user->id, ':app' => $app, ':version' => $system->app_version($app), ':loaded' => $loaded]);
					if(!$insert->success) return $database->rollback() && self::$_mode['error'];
				}

				if(!$database->commit()) return $database->rollback() && self::$_mode['error']; //Commit the previous batch of queries
			}

			$database = $system->database('user', __METHOD__, null, null, null, true); //Try to reopen user's login database

			if(!$database->success)
			{
				unset($_COOKIE['identity'], $_COOKIE['ticket']); //Cancel the cookie values set temporarily on failure to quit
				return self::$_mode['error']; //Report error
			}
		}

		$log->system(LOG_INFO, "Sending session cookie for user '$identity'");
		$end = $keep ? time() + System_1_0_0_User::$_last * 24 * 3600 : 0; //Set the session to be kept or not

		//Issue the cookies - NOTE : Passing both the ticket and the identity instead of just the ticket to make it difficult to brute force attack on ticket value
		$system->cookie_set('identity', $identity, $end);
		$system->cookie_set('ticket', $ticket, $end);

		if(!$conf['system_demo'])
		{
			//Record login history to the user's database
			$query = $database->prepare("INSERT INTO {$database->prefix}record (user, time, ip, success) VALUES (:user, :time, :ip, :success)");

			//Ignore errors happened in here
			$query->run([':user' => $id, ':time' => $time, ':ip' => $_SERVER['REMOTE_ADDR'], ':success' => !!$response]);
		}

		return self::$_mode['success']; //Report success
	}

	public static function auth($identity, $pass) //Check if the login succeeds or not (And create the user if necessary) and returns the user ID
	{ //TODO - limit amount of login attempt in a certain time frame (ex : 5 times within 3 min)
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		$conf = $system->app_conf('system', 'static');

		if(!System_Static::auth_verify($identity = strtolower($identity), $pass)) //Look up for authorization
			return $log->system(LOG_NOTICE, "User authorization failed for user '$identity'", 'Check login credentials');

		//If set to create the user automatically, try to create one, otherwise look for an existing one and return the user ID
		return $conf['user_create'] && !$conf['system_demo'] ? $system->user_create($identity, $pass) : $system->user_find($identity)->id;
	}

	public static function process($identity, $pass, $keep) //Sends back the authorization result (0 : success, 1 : failure, 2 : error)
	{
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		$identity = strtolower($identity);

		if(!$system->is_identity($identity) || !$system->is_text($pass)) $status = 1; //Report failure
		else
		{
			$log->system(LOG_INFO, 'Checking for user credentials');

			$status = self::attempt($identity, $pass, $keep); //Try the login sequence (Will be issued cookies on success)
			$conf = $system->app_conf('system', 'static');

			//Since cookies are already issued and login is recorded in the database,
			//it will be odd to issue status code other than 0 beyond this point,
			//but in rare cases that something fails during this period, status 2 is sent
			//and the client side should handle what to do in that case
			if($status === 0 && !$conf['system_demo']) //On success
			{
				$log->dev(LOG_INFO, 'Getting the last logged in information');

				$user = $system->user(null, true); //Get the logged in user on success
				$database = $system->database('user', __METHOD__, $user); //Open the login database

				if(!$user->valid || !$database->success) $status = 2; //If somehow the user is invalid after a successful login
				else
				{
					//Retrieve the last login record to show to the user
					$query = $database->prepare("SELECT time, ip FROM {$database->prefix}record WHERE user = :user AND success = 1 ORDER BY id DESC LIMIT 1,1");
					$query->run([':user' => $user->id]);

					if($query->success) $row = $query->row(); //Get a row from the result
					else $status = 2; //If database query fails, report error
				}
			}
		}

		if($status == 0) $info = $system->xml_node('login', ['time' => $row['time'], 'ip' => $row['ip']]); //Create login XML
		if($info === false) $status = 2;

		return ['status' => $status, 'data' => $info];
	}
}

<?php
	class System_Static_Auth
	{
		public static function verify($identity, $password) //Check if authentication passes with given parameters
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!is_string($identity) || !is_string($password)) return $log->param();

			$conf = $system->app_conf('system', 'static');
			$identity = strtolower($identity);

			if(!in_array($conf['user_hash'], hash_algos())) //If used password hashing algorithm is not implemented, quit
			{
				$problem = 'Specified password hashing algorithm (\'user_hash\' configuration value) is not implemented in PHP';
				return $log->system($problem, 'Use an algorithm implemented in PHP or enable such algorithm in PHP');
			}

			$database = $system->database('system', __METHOD__, null, 'system', 'static'); //Open system database
			if(!$database->success) return false;

			//Look for the user with the given user name and password
			$query = $database->prepare("SELECT id FROM {$database->prefix}user WHERE mail = :mail AND password = :password AND invalid = 0");
			$query->run(array(':mail' => $identity, ':password' => hash($conf['user_hash'], $password))); //Run the query

			if($query->success) $id = $query->column(); //Get the user information

			//If the user ID cannot be found, quit
			if(!$system->is_digit($id)) return $log->system(LOG_NOTICE, 'User not found in system', 'Check for user');

			return true; //If the user is found, report success
		}
	}

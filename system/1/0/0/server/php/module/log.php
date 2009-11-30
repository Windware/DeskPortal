<?php
	class System_1_0_0_Log
	{
		private $_caller, $_source, $_system; #Privately used variable to store the origin

		protected function _take($section, $level, $message, $solution, $value) #Internal function to take a log to a database
		{
			static $_drop = false; #Flag to keep this function from looping inside itself
			$system = $this->_system;

			#Avoid log looping by ignoring the process partially when errors occur inside this function
			if($_drop) $ignore = true; #If error occured within the function, start ignoring parts of it
			else $_drop = true; #Keep the drop flag on to catch looping

			if(!preg_match('/^[0-7]$/', $level)) #Give a warning level in case the level is not set
			{
				$level = LOG_WARNING;
				$message .= ' (Log level was not properly set)';
			}

			if($message == '') $message = '(No message was specified)'; #Log about message being empty
			$folder = $system->file_relative(getcwd()).'/'; #Current folder

			if(!$ignore) #If no other logging is happening currently
			{
				if($system->global['param']['periodic']) #If under periodic scheduler execution
				{
					$static = System_Static::log($this->_source[0], __CLASS__);
					$static->periodic($level, $message, $solution, null, $section, $written); #Write to log

					return $value; #Return value
				}

				if($section != 'system') #For user logs
				{
					$user = $system->user(); #Get the current user
					if(!$user->valid) $section = 'system'; #If user is not logged in, log to system database instead
				}

				$conf = $system->app_conf('system', 'static');

				#Write logs according to specified section if level is above configured threshold (0 : system, 1 : user, 2 : dev)
				#Note that any external function within this block will also trigger 'take' at certain logging level
				#and those will be taken under "else" statement to avoid looping in case error happens then
				if($section == 'system')
				{
					if($level <= $conf['log_system']) #If should be recorded, build up the parameters
					{
						$columns = '(session, ip, time, level, access, reporter, app, version, source, folder, message, solution)';
						$values = '(:session, :ip, current_timestamp, :level, :access, :reporter, :app, :version, :source, :folder, :message, :solution)';

						$component = array(':session' => $system->global['define']['id'], ':ip' => $_SERVER['REMOTE_ADDR'],
						':level' => $level, ':access' => $system->global['define']['self'], ':reporter' => $system->system['id'],
						':app' => strtolower($this->_source[2]), ':version' => strtolower($this->_source[3]), ':source' => $this->_source[0],
						':folder' => $folder, ':message' => $message, ':solution' => $solution);

						$database = $system->database('system', __METHOD__, null, 'system', 'static'); #Open system database
					}
				}
				elseif($level <= $conf['log_user']) #If should be recorded, build up the parameters
				{
					$columns = '(user, section, session, ip, time, level, access, reporter, app, version, source, folder, message, solution)';
					$values = '(:user, :section, :session, :ip, current_timestamp, :level, :access, :reporter, :app, :version, :source, :folder, :message, :solution)';

					$component = array(':user' => $user->id, ':section' => $section, ':session' => $system->global['define']['id'],
					':ip' => $_SERVER['REMOTE_ADDR'], ':level' => $level, ':access' => $system->global['define']['self'],
					':reporter' => $system->system['id'], ':app' => strtolower($this->_source[2]), ':version' => strtolower($this->_source[3]),
					':source' => $this->_source[0], ':folder' => $folder, ':message' => $message, ':solution' => $solution);

					$database = $system->database('user', __METHOD__, $user, 'system', 'static'); #Open logged in user's database
				}

				if($database->success) #If the database could be opened
				{
					#Make the query for storing into the log, but don't log the query to avoid looping
					$query = $database->prepare("INSERT INTO {$database->prefix}log $columns VALUES $values", LOG_DEBUG);
					$query->run($component);

					if(!$query->success) #Report about the logging failure
					{
						$problem = "Logging to $section's database failed with error code : ".$query->error->getMessage();

						$static = System_Static::log($this->_source[0], $system->system['id']);
						$static->system(LOG_ERR, $problem, 'Check database settings for logging', null, $section);
					}
					else $written = true; #Do not write the log to file as well if recorded in database
				}

				$_drop = false; #Clear the lock
			}

			#Print to display and keep in global variable
			$static = System_Static::log($this->_source[0], __CLASS__);
			$static->system($level, $message, $solution, null, $section, $written);

			return $value; #Return the given value back as is
		}

		public function __construct(&$system, $caller)
		{
			$this->_caller = $system->file_relative($caller); #Remember where it originated from

			#Extract the application name, version and method name from the given origin
			preg_match('/^(([a-z\d]+?)_(\d+_\d+|Static)(_.+?)?)::.+$/i', $this->_caller, $this->_source);

			if(!$this->_source) #If reported from a code that is not within a class method
			{
				#Extract the application name, version and file path from the given origin
				preg_match('!^([a-z\d]+)/(\d+/\d+|static)/.+!', $this->_caller, $matches);
				$this->_source = array($matches[0], null, $matches[1], str_replace('/', '_', $matches[2]));
			}

			$this->_system = $system; #Let other function have access to the other system functions
			$this->dev(LOG_DEBUG, "Starting to log for '$this->_caller'"); #Notfiy the start of logging
		}

		public function dev($level, $message, $solution = '', $value = false) #Front end to take log for developer message
		{
			return $this->_take('dev', $level, $message, $solution, $value);
		}

		public function param($value = false) #Front end to take log for when a method is called with invalid parameters
		{
			return $this->dev(LOG_WARNING, 'Wrong parameter passed', 'Check for the parameter validity', $value);
		}

		public function system($level, $message, $solution = '', $value = false) #Front end to take log of system
		{
			return $this->_take('system', $level, $message, $solution, $value);
		}

		public function user($level, $message, $solution = '', $value = false) #Front end to take log for user message
		{
			return $this->_take('user', $level, $message, $solution, $value);
		}
	}
?>

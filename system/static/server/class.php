<?php
function __autoload($class) //Automatically load the class files when necessary
{
	//TODO - Check PHP version and redirect to specific files
	//ex : use php/module/cache_5.php if the PHP version is 5.X and above and php/module/cache_5_2.php for PHP 5.2.X and above

	$log = System_Static::log(__METHOD__);

	if(!preg_match('/^([a-z\d]+_\d+_\d+_\d+)($|_(\w+)$)/i', $class, $matches)) //Scoop out the class information
		return $log->system(LOG_ERR, "Class used has an invalid name syntax '$class'", 'Check for the class name');

	$top = System_Static::$global['define']['top'];
	$base = strtolower(str_replace('_', '/', $matches[1])) . '/'; //The application base folder

	if(!isset($matches[3])) $class = 'server/php/init.php'; //If base class for the application was called
	else $class = 'server/php/module/' . strtolower($matches[3]) . '.php'; //If module class was called

	if(!System_Static::file_readable($top . $base . $class))
	{
		$solution = 'Make sure a proper class is specified and the class file exists';
		return $log->system(LOG_ERR, "Cannot load '$base$class' class file", $solution);
	}

	return require_once $top . $base . $class; //Load the class file
}

class System_Static
{
	public static $global; //Globally used variable collection

	public static function app_conf($name, $version, $key = null, $value = null) //Retrieves app conf and optionally overwrite the value
	{
		$log = self::log(__METHOD__);
		if(!self::is_app($name) || $version !== 'static' && !preg_match('/^\d+/', $version) || $key !== null && !is_string($key)) return $log->param();

		$id = "{$name}_$version";
		$conf = &self::$global['conf']; //Reference to the configuration hash

		//If not cached yet, read the config and create cache
		if(!isset($conf[$id])) $conf[$id] = self::file_conf("system/static/conf/app/$id.xml");
		if(!is_array($conf[$id])) return false;

		if($id === 'system_static')
		{
			if($conf[$id]['system_demo']) $conf[$id]['db_lock'] = '1'; //Force turn on database locking under demo mode
			if($conf[$id]['db_lock']) $conf[$id]['db_persistent'] = '0'; //Force turn off database persistent mode when under lock mode to avoid transaction dragging
		}

		if($key === null) return $conf[$id]; //Send the entire hash
		elseif($value === null) return $conf[$id][$key]; //Send only the given key value

		$current = $conf[$id][$key]; //Keep the current value
		$conf[$id][$key] = $value; //Update the value

		return $current; //Return the previous value
	}

	public static function app_env($path) //Returns the application environment from a given path
	{
		$log = self::log(__METHOD__);
		$path = self::file_relative($path); //Make it a relative path from the top folder

		if(!preg_match('|^(([a-z\d]+)/((\d+)/\d+/\d+)/)(.+)|', $path, $matches) && !preg_match('|^((system)/(static)/)(.+)|', $path, $matches))
			return $log->param(); //Match the path according to directory structure

		$env = []; //Application environment information

		//Set various properties
		$env['name'] = $matches[2]; //The name of the application
		$env['version'] = str_replace('/', '_', $matches[3]); //The version of the application

		$env['id'] = $env['name'] . '_' . $env['version']; //Application ID
		$env['db'] = $matches[4]; //Database version to use

		$env['root'] = self::$global['define']['top'] . $matches[1]; //Root directory for the application
		$env['path'] = @$matches[5]; //Requested path from the root directory above

		return $env; //Return the informartion
	}

	public static function auth_verify($user, $password)
	{
		$log = self::log(__METHOD__);
		$conf = self::app_conf('system', 'static');

		if(preg_match('/[^\w\-]/', $method = $conf['system_auth'])) return $log->system(LOG_ERR, 'Invalid authentication mechanism specified', 'Check for system configuration');

		$auth = "system/static/server/auth/$method.php"; //Authentication method script
		$log->system(LOG_INFO, "Loading authentication configuration file '$auth'");

		require_once(self::$global['define']['top'] . $auth); //Load the file
		return System_Static_Auth::verify($user, $password); //Run the authorization check
	}

	public static function cache_operate($id, $op, $key, $data = null, $compressed = false)
	{
		$log = self::log(__METHOD__);

		if(!self::app_conf('system', 'static', 'cache'))
			return $log->system(LOG_NOTICE, 'Cache not allowed by system configuration', 'Enable caching in system configuration');

		//Make sure to only allow valid operations
		if(!in_array($op, ['get', 'modified', 'set'])) return $log->param();

		if(!is_string($key) || $data !== null && !is_string($data)) return $log->param();
		if(!is_string($id) || $id !== 'system_static' && !preg_match('/^[a-z\d]+_\d+_\d+_\d+$/', $id)) return $log->param();

		//Pick the cache operation function code
		$code = 'system/static/server/cache/' . self::app_conf('system', 'static', 'cache_method') . '.php';

		if(!self::file_readable(self::$global['define']['top'] . $code))
		{
			$solution = 'Make sure a right cache function is used and the file exists for it';
			return $log->system(LOG_ERR, "Cache function code '$code' could not be read", $solution);
		}

		$log->system(LOG_INFO, "Loading a cache function script '$code'");
		require_once(self::$global['define']['top'] . $code); //Load the function library

		if(!in_array($op, ['get', 'modified', 'set'])) return $log->param(); //Just in case the variable gets tainted - FIXME : Could try to prevent it
		return System_Static_Cache::$op($id, $key, $data, $compressed); //Run the code
	}

	public static function event_run() //Periodic schedule action launcher
	{
		$system = new System_1_0_0(__FILE__); //Use a higher level system version
		$system->event_run();
	}

	public static function file_conf($xml) //Read XML config file and return a hash
	{
		$log = self::log(__METHOD__);
		if(!is_string($xml) || preg_match('!(^|/)\.\.(\/|$)!', $xml)) return $log->param();

		$xml = System_Static::$global['define']['top'] . $xml;
		$settings = []; //Settings retrieved

		if(!self::file_readable($xml))
			return $log->system(LOG_NOTICE, "Config file '$xml' not found", 'Make sure the XML config file exists');

		preg_match_all('|<conf\s+name="(.+?)"\s+value="(.+?)"|', file_get_contents($xml), $matches); //Pick the XML values
		$conf = self::$global['conf']['system_static']; //System configuration

		for($i = 0; $i < count($matches[1]); $i++) //Iterate over the matched array and create an array and replace variables
		{
			if($conf) $matches[2][$i] = preg_replace('/%([a-z_]+?)%/e', '$conf["$1"]', $matches[2][$i]); //Replace variables
			$settings[$matches[1][$i]][] = $matches[2][$i]; //Extract the configuration key/value from the matches
		}

		if(!count($settings))
		{
			$problem = "Could not retrieve any value from the config XML '$xml'";
			return $log->system(LOG_WARNING, $problem, 'Make sure the XML config file has proper values set');
		}

		//If there's only one element in an array, make it accessible directly instead of as a first element in the array
		foreach($settings as $index => $param) if(count($param) === 1) $settings[$index] = $param[0];

		return $settings;
	}

	//Loads a file
	public static function file_load($file, $multiple = false, $severity = LOG_ERR) //FIXME - included file has too many variables visible from within this function
	{
		$log = self::log(__METHOD__);
		if(!is_string($file)) $log->param();

		$return = $multiple ? @include($file) : @include_once($file); //Load the specified file
		return $return !== false ? true : $log->system($severity, 'Cannot load file : ' . self::file_relative($file), 'Make sure it is accessible');
	}

	//Checks whether the file is readable or not
	public static function file_readable($file)
	{
		return is_string($file) && is_readable($file) && is_file($file);
	}

	public static function file_relative($path) //Returns the relative path from the system root from an absolute path
	{
		if(!is_string($path)) return '';

		$relative = preg_replace('/^' . preg_quote(self::$global['define']['top'], '/') . '?/', '', $path); //Strip the top path
		return $relative ? $relative : '.'; //Return the path and give self path if the string became empty
	}

	public static function is_app($name, $version = null) //Returns if the given string is a proper existing application name
	{
		//Returns if the given name is a readable application folder
		if(!is_string($name) || !preg_match('/^[a-z][a-z\d]+$/', $name)) return false;
		return $version === null || self::is_version($version);
	}

	public static function is_id($id) //Returns if the given string is a valid ID for an existing application
	{
		if(!is_string($id)) return false;

		$values = explode('_', $id, 2); //Split the name and the version
		return self::is_app($values[0], $values[1]);
	}

	public static function is_path($subject) //Checks if the value is a valid relative path from the system root directory
	{
		return is_string($subject) && (preg_match('/^[a-z\d]+(\/\d+){3}\//', $subject) || preg_match('/^system\/static\//', $subject));
	}

	public static function is_version($subject) //Returns if the given string is a valid version string
	{
		return is_string($subject) && (preg_match('/^\d+_\d+_\d+$/', $subject) || $subject === 'static');
	}

	public static function is_user($subject)
	{
		return !!preg_match('/^\w+$/', $subject);
	} //Returns if the given string is a proper user name

	public static function log($caller, $reporter = null)
	{
		static $_log;

		if($reporter === null) $reporter = __CLASS__; //Set self as the reporter if not specified
		if(!is_string($caller) || !is_string($reporter)) return false;

		$id = md5($caller . $reporter); //Set unique ID for object caching

		if(@$_log[$id] instanceof System_Static_Log) return $_log[$id]; //Use already created log object for the function
		return $_log[$id] = new System_Static_Log($caller, $reporter); //Create log object
	}

	public static function system_load() //Load the system : TODO - Need DoS protection
	{
		$global = &System_Static::$global;
		$log = System_Static::log(__FILE__); //Initialize logging

		chdir($global['define']['top']); //Set initial working directory to the system root

		if(!System_Static::app_env($_GET['_self'])) //Check for '_self' variable sanity
		{
			//Log the error. Do NOT add variables like '$_GET' in the log to avoid log flooding until it's verified as a sane value
			return $log->system(LOG_ERR, 'Improper file specified', 'Check for \'_self\' parameter');
		}

		$global['define']['self'] = $_GET['_self']; //Keep the requested parameter as a reference
		$directory = 'system/' . str_replace('_', '/', $_GET['_version']) . '/'; //Construct the system version directory

		//TODO - Use the static configuration version of system, if '_version' is unspecified for external requests

		//Find out which system version to pass processing to from client's request
		if(!preg_match('/^\d+_\d+_\d+$/', $_GET['_version']) || !is_readable($directory))
			return $log->system(LOG_ERR, 'Improper version specified', 'Check for \'_version\' parameter');

		$log->system(LOG_INFO, "Passing processing to specified system version '{$_GET['_version']}'");

		unset($_GET['_self'], $_GET['_version']); //No longer needed and to give cleaner $_GET to following scripts
		System_Static::file_load("{$directory}server/php/router.php"); //Turn processing over to the specified system
	}

	public static function system_strip($target = null) //Remove magic quotes
	{
		if($target === null)
		{
			$_GET = self::system_strip($_GET);
			$_POST = self::system_strip($_POST);
			$_COOKIE = self::system_strip($_COOKIE);

			return;
		}

		if(is_array($target)) return array_map(['self', 'system_strip'], $target);
		return stripslashes($target);
	}
}

class System_Static_Log //Logging class
{
	private $_caller, $_reporter;

	public $invalid = false;

	public function __construct($caller, $reporter)
	{
		$this->_caller = System_Static::file_relative($caller); //Remember which function initialized the class
		$this->_reporter = $reporter; //Set who reported the error

		if($this->_caller === '' || $this->_reporter === '') $this->invalid = true; //Invalidate bad logging
		$this->system(LOG_DEBUG, "Starting to log for '$this->_caller'");
	}

	public function param($value = false) //Log about invalid parameters
	{
		return $this->system(LOG_WARNING, 'Wrong parameter passed', 'Check for the parameter validity', $value);
	}

	public function periodic($level, $message, $solution = '', $value = false) //Log about periodic schedulers
	{
		return $this->system($level, $message, $solution, $value, 'periodic');
	}

	//The base logging capability to show errors on display and to write any unwritten log to a file as a fallback
	public function system($level, $message, $solution = '', $value = false, $section = 'system', $written = false)
	{
		static $count; //Log count
		$count++; //Increment log count

		if(!count(System_Static::$global['conf']['system_static'])) return $value; //Quit if config values are not ready

		$conf = System_Static::app_conf('system', 'static'); //Load the configs
		$global = &System_Static::$global; //Shortcut to globally used variables

		if($level == LOG_EMERG && !$conf['log_query']) return $value; //If queries are not supposed to be logged, quit

		//Set the conditions for logging
		$report['display'] = $conf['log_display'] >= $level;

		if($section === 'system') $report['log'] = $level <= $conf['log_system'];
		if($section === 'periodic') $report['log'] = $level <= $conf['periodic_level'];
		else $report['log'] = $level <= $conf['log_user'];

		if(!$report['display'] && !$report['log']) return $value; //If not reporting for anything, quit

		$folder = System_Static::file_relative(getcwd()) . '/'; //Get current working folder
		if($level == LOG_CRIT) $message .= ' : Quitting the process due to a critical error.'; //Add message for critical conditions

		//The log components
		$component = [$count, $global['define']['log'][$level] . "($level)", $section, $this->_reporter, $this->_caller, $folder, $message, $solution];
		$log = '| ' . implode(' | ', $component) . ' |'; //Concatenate the log components

		$mode = php_sapi_name(); //Check how PHP ran

		if($report['display']) //If configured level is higher to display logs
		{
			if(!$global['param']['log_header']) //If the header is not displayed yet
			{
				$description = 'No. Level Section Reporter Origin Folder Message Solution';

				//Print the message header
				if(!$conf['log_html'] || $mode === 'cli') print '| ' . str_replace(' ', ' | ', $description) . " |\n\n";
				else //In HTML format (Note that the '<table>' tag cannot be closed)
				{
					print "<table style=\"empty-cells : show\" border=\"1\" cellpadding=\"5\" cellspacing=\"0\">\n";
					print '<tr style="background-color : #fea"><th>' . str_replace(' ', '</th><th>', $description) . "</th></tr>\n";
				}

				$global['param']['log_header'] = true; //Specify its existence
			}

			if(!$conf['log_html'] || $mode === 'cli') print "$log\n"; //Print out the log
			else //In HTML format
			{
				$severity = ['ccc', '', 'f85', 'f95', 'cba', 'abc', 'fff', 'eee']; //Colorize the levels
				print "<tr style=\"background-color : #{$severity[$level]}\"><td>" . implode('</td><td>', array_map('htmlspecialchars', $component)) . "</td></tr>\n";
			}

			//If worse than specified level for detailed output, print the backtrace too (LOG_EMERG is for query logging)
			if($level <= $conf['log_detail'] && $level != LOG_EMERG) print_r(debug_backtrace());

			flush(); //Send the debug message as soon as possible
		}

		if(!$report['log']) return $value; //If level is higher than logging threshold, quit

		if(!$written && $conf['log_file']) //If supposed to write (To avoid log loop)
		{
			$content = "$log {$global['define']['id']} | " . date('r') . " | {$_SERVER['REMOTE_ADDR']} |\n"; //Log line (NOTE : Do not use 'gmdate' here)
			$file = $section === 'periodic' ? $conf['periodic_log'] : $conf['log_file'];

			if(!file_put_contents($file, $content, FILE_APPEND)) //If it cannot write to the log file
			{
				$message = 'Cannot store log at : ' . System_Static::file_relative($file);

				//Report that log could not be written and do not try to loop by setting '$written' value to 'true'
				$this->system(LOG_ERR, $message, 'Check for permissions and path to the log file', false, 'system', true);
			}
		}

		if($level == LOG_CRIT) exit; //If caught a fatal error, exit out completely
		return $value; //Return the passed value back
	}
}

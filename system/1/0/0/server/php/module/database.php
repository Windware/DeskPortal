<?php
	class System_1_0_0_Database
	{
		private $_system;

		protected static $character = '$'; #Escape character for 'LIKE' statement special characters ('%' and '_')

		public $adapter, $caller, $handler, $logger, $prefix, $app, $success, $type, $user, $version;

		protected function _build($structure) #Create a table from a schema object
		{
			if(!is_object($structure)) return false;

			foreach($structure->table as $table) #For each of the table definition
			{
				if($init = System_Static_Database::init($this->adapter))
				{
					$query = $this->prepare($init); #Database initialization if any exists
					$query->run();

					if(!$query->success) return $this->logger(LOG_CRIT, 'Database initialization query failed', 'Check for database initialization code and database configuration');
				}

				$element = $query = array(); #SQL table field description and queries
				$extra = ''; #SQL table settings and indexes

				foreach($table->column as $column) $element[] = "{$column['name']} {$column['param']}"; #List up columns
				foreach($table->extra as $setting) $extra .= " {$setting['name']}={$setting['param']}"; #List up table settings

				$character = System_Static_Database::charset($this->adapter); #Get the character set declaration string
				$name = strlen($table['name']) ? $this->prefix.$table['name'] : substr($this->prefix, 0, -1);

				#Create the table : TODO - Do string sanity check from the schema file
				$query['create'] = $this->prepare("CREATE TABLE IF NOT EXISTS $name (".implode(', ', $element).") $character$extra");
				$query['create']->run();

				if(!$query['create']->success)
				{
					$problem = "Tables failed to be created. All partial tables of '$this->prefix*' must be deleted manually.";
					return $this->logger(LOG_CRIT, $problem, 'Check for database configuration and the schema');
				}

				$count = 0; #Index counter

				foreach($table->index as $setting)
				{
					$count++;

					$query['index'] = $this->prepare("CREATE INDEX {$name}_$count ON $name ({$setting['name']})"); #Set indexes
					$query['index']->run();

					if($query['index']->success) continue;

					$problem = "Table indexes failed to be created. All partial tables of '$this->prefix*' must be deleted manually.";
					return $this->logger(LOG_CRIT, $problem, 'Check for database configuration and the schema');
				}
			}

			return true;
		}

		protected function _create() #To check and create application database tables
		{
			$base = substr($this->prefix, 0, -1); #The base part of hte table name
			$this->logger(LOG_INFO, "Checking for the table '$base' availability");

			try #TODO - Better way to detect table's presence in a database compatible way?
			{
				$statement = $this->handler->prepare("SELECT name FROM $base LIMIT 1"); #Create prepared statement
				$statement->execute(); #Run the query

				return $this->logger(LOG_NOTICE, "The table '$base' already exists", '', true); #If the table exists, report success
			}

			catch(PDOException $error) { } #If the table does not exist, continue on
			$this->logger(LOG_INFO, 'Creating tables');

			if($this->version != 'static') $inner = '/0/0'; #Choose the base version where database schema exists

			$specific = "$this->app/$this->version$inner/base/schema/{$this->type}_$this->adapter.xml"; #App's database schema
			$base = "system/static/base/schema/{$this->type}_common_$this->adapter.xml"; #Base database schema

			$schema = $this->_system->file_readable($this->_system->global['define']['top'].$specific);

			if($schema && !$this->_build($this->schema($specific)) || !$this->_build($this->schema($base)))
			{
				#NOTE : MySQL does not support rollback on "CREATE TABLE" statement and since manually removing
				#tables those might have data is dangerous, no rollback is performed when this operation fails
				$problem = "Tables failed to be created. All tables of '$this->prefix*' must be deleted manually : $error";
				return $this->logger(LOG_CRIT, $problem, 'Check for database configuration and the schema files');
			}

			return true;
		}

		#Database object initialization by making a connection to the database
		public function __construct(&$system, $type, $caller, System_1_0_0_User $user = null, $app, $version)
		{
			$this->_system = $system; #Let other functions access the system object
			$this->success = false; #Keep the default value

			$log = $system->log(__METHOD__);
			$this->caller = $caller; #The function that asked for database connection

			if(!$system->is_app($app) || ($version != 'static' && !$system->is_digit($version))) return $log->param();

			if($type == 'system') #If opening the system database
			{
				#Set the database application as static version of system
				$params = 'db_system_type db_system_host db_system_port db_system_database db_system_user db_system_pass'; #Pick the configuration keys
				$sector = 'system'; #Indicate opening the system database
			}
			elseif($type == 'user') #If opening an user's database
			{
				$params = 'db_user_type db_user_host db_user_port db_user_database db_user_user db_user_pass'; #Pick the configuration keys
				$sector = 'user'; #Indicate opening the user database

				if($user === null) $user = $system->user(); #If not specified, get the logged in user object

				if(!$user->valid) #If user object cannot be reteived, quit
				{
					$problem = 'Cannot get user object to connect to the user database';
					return $log->dev(LOG_ERR, $problem, 'Make sure right user is specified or the user is logged in');
				}
			}

			$conf = $system->app_conf('system', 'static');
			$info = array();

			foreach(explode(' ', $params) as $value) $info[] = $conf[$value]; #Pick settings
			list($adapter, $host, $port, $database, $name, $pass) = $info; #Give better names to the configuration parameters

			#Set database information as the object properties
			$this->app = $app;
			$this->version = $version;

			$this->user = $user;
			$this->type = $type;
			$this->adapter = $adapter;

			$this->escape = "ESCAPE '".self::$character."'"; #The escape character for 'LIKE' statements
			$this->logger(LOG_INFO, 'Creating a new database object');

			#Replace the placeholders passed with real values : TODO - Do string sanity check on replacements
			$database = str_replace(array('%APP%', '%VERSION%'), array($app, $version), $database);

			if($sector == 'user')
			{
				$database = str_replace('%USER%', $user->name, $database); #Replace user's name for an user database
				if($adapter == 'sqlite' && !is_dir(dirname($database))) mkdir(dirname($database), 0777, true); #Try to create the directory for sqlite
			}

			$this->prefix = "{$conf['db_prefix']}{$app}_{$version}_"; #Set table prefix

			#Set up the PDO string. Since the format may vary from use to use, it is set placed configuration folder
			$setting = 'system/static/conf/database/database.php';
			$this->logger(LOG_INFO, "Loading database connection configuration file '$setting'");

			require_once($system->global['define']['top'].$setting); #Load database specific connection configuration

			#Get the connection string
			$method = System_Static_Database::connection($sector, $adapter, $host, $port, $database);
			$this->logger(LOG_INFO, 'Trying to get a new database connection');

			try #Open up a database connection
			{
				#NOTE : Avoid persistent connection under locked mode as trasnaction can possibly drag over to the next connection
				$this->handler = new PDO($method, $name, $pass, array(PDO::ATTR_PERSISTENT => !!$conf['db_persistent']));
				$this->handler->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			}

			catch(PDOException $error)
			{
				$this->handler = null; #Discard the database connection object
				return $this->logger(LOG_ERR, 'Cannot open database connection : '.$error->getMessage(), 'Check the error');
			}

			try
			{
				$statement = $this->handler->prepare('SET NAMES utf8');
				$statement->execute(); #Set the character encoding for the connection
			}

			catch(PDOException $error) #Make a note about the failure but do not stop
			{
				$problem = "Database does not support setting the encoding through 'SET NAMES utf8' query";
				$this->logger(LOG_NOTICE, $problem, 'This error is ignored for databases that cannot set client character encoding');
			}

			$this->logger(LOG_INFO, 'Connection successful');
			$this->success = true; #Report the success for opening the database

			if($conf['db_lock']) $this->begin(); #Avoid database changes under locked mode and make all operations under a single transaction to be rolled back
			$this->locked = $conf['db_lock']; #No write locked mode

			return $this->_create(); #Check for table availability
		}

		public function __destruct() { if($this->locked && $this->success) return $this->rollback(); } #Rollback changes under locked mode


		public function begin() #Start a transaction
		{
			if(!$this->success) return false;
			if($this->locked) return true; #Do not allow transaction on locked mode

			try { return $this->handler->beginTransaction(); }

			catch(PDOException $error) { return $this->logger(LOG_ERR, 'Cannot start a transaction : '.$error->getMessage(), 'Check the error'); }
		}

		public function commit() #Commit the changes atomically
		{
			if(!$this->success) return false;
			if($this->locked) return true; #Do not allow transaction on locked mode

			try { return $this->handler->commit(); }

			catch(PDOException $error) { return $this->logger(LOG_ERR, 'Cannot commit changes of a transaction : '.$error->getMessage(), 'Check the error'); }
		}

		#Escape string for 'LIKE' operators
		public static function escape($string) { return is_string($string) ? str_replace(array(self::$character, '%', '_'), array(self::$character.self::$character, self::$character.'%', self::$character.'_'), $string) : ''; }

		#Retrieve or store a value with a key index : TODO - Make it deletable
		public static function key(&$system, $type, $key, $value = null, System_1_0_0_User $user = null, $app = null, $version = null)
		{
			static $query; #Store prepared statements
			$log = $system->log(__METHOD__);

			if(!$system->is_text($key) && !$system->is_digit($key)) return $log->param();
			if($value !== null && !$system->is_text($value, true) && !$system->is_digit($value)) return $log->param();

			if($app === null) $app = $system->self['name']; #App name
			if(!$system->is_app($app)) return $log->param();

			if($version === null) $version = $system->self['db']; #DB version
			if(!$system->is_digit($version) && "{$app}_$version" != 'system_static') return $log->param();

			$data = array(':name' => $key); #Data for the query

			if($type == 'user') #For intereacting with user database
			{
				if($user === null) $user = $system->user();
				if(!$user->valid) return false;

				#Parameter additions to specify the user
				$limit = ' user = :user AND';
				$column = 'user, ';
				$placeholder = ':user, ';

				$data[':user'] = $user->id;
			}

			$id = "$user->id-$app-$version-".($value === null ? 0 : 1); #Query specific ID

			if(!$query[$id]) #Create a prepared statement and store it
			{
				$database = $system->database($type, __METHOD__, $user, $app, $version);
				$table = substr($database->prefix, 0, -1);

				if($value === null) $sql = "SELECT value FROM $table WHERE$limit name = :name"; #For retrieving the value
				else #For storing a value
				{
					$sql = "REPLACE INTO $table ({$column}name, value) VALUES ($placeholder:name, :value)";
					$data[':value'] = $value;
				}

				$query[$id] = $database->prepare($sql);
			}

			$query[$id]->run($data);
			if(!$query[$id]->success) return false;

			return $value === null ? $query[$id]->column() : true;
		}

		public function id() { return $this->success ? $this->handler->lastInsertId() : null; } #Get the last inserted row's ID

		public function log($query, $parameters, $level = LOG_EMERG) #Log the query
		{
			$conf = $this->_system->app_conf('system', 'static');
			$conf = (int) $conf['log_query']; #Configured verbosity level on queries

			if(is_string($query))
			{
				if($conf >= 2 && is_array($parameters)) #Use detailed logging if specified by replacing the parameters with values
				{
					foreach($parameters as $key => $value)
					{
						if(!is_string($key)) continue; #For odd cases, quit

						if(is_string($value)) $value = "'".addslashes($value)."'"; #Quote the string type values
						elseif(is_null($value)) $value = 'NULL';

						$query = str_replace($key, $value, $query); #Replace the parameters
					}
				}
			}
			else
			{
				$query = '(Invalid query)'; #Report invalid query
				$parameters = array();
			}

			if($conf >= 1) $this->logger($level, "Making query : [$query]"); #Log the query
		}

		public function logger($level, $message, $solution = '', $value = false) #Log with database information
		{
			$log = $this->_system->log(__METHOD__);

			#Add database info
			$message .= " on $this->type database for application '$this->app' version '$this->version' from '$this->caller'";

			return $log->dev($level, $message, $solution, $value); #Report the error with the error message given
		}

		public function rollback() #Rollback the transaction
		{
			if(!$this->success) return false;
			if($this->locked) return true; #Do not allow transaction on locked mode

			try { return $this->handler->rollback(); }

			catch(PDOException $error) { return $this->logger(LOG_ERR, 'Cannot rollback changes of a transaction : '.$error->getMessage(), 'Check the error'); }
		}

		#Make a prepared statement (Second parameter is only used from the logging module to avoid looping)
		public function prepare($query, $level = LOG_EMERG) { return new System_1_0_0_Database_Query($this, $query, $level); }

		public function schema($schema) #Read database schema file and parse its XML
		{
			$system = $this->_system;
			if(!$system->is_path($schema)) return false;

			if(!$system->file_readable($system->global['define']['top'].$schema)) #If schema file cannot be read, quit
				return $this->logger(LOG_ERR, "Schema file cannot be read '$schema'", 'Make sure it exists');

			#Parse the XML and return the content
			try { return new SimpleXMLElement($system->file_read($system->global['define']['top'].$schema)); }

			catch(Exception $error)
			{
				$problem = "Table schema file '$schema' cannot be parsed : ".$error->getMessage();
				return $this->logger(LOG_ERR, $problem, 'Check for schema file structure');
			}
		}
	}

	class System_1_0_0_Database_Query #An object to be built for each queries
	{
		private $database, $level;

		protected $_mode = PDO::FETCH_ASSOC;

		public $error, $handler, $query; #Error string if any, PDO statement object itself and the query string

		public $success = false; #If the query succeeded or not

		public function __construct(&$database, $query, $level)
		{
			$database->logger(LOG_INFO, "Creating a prepared statement [$query]");

			$this->database = $database; #Reference to the database object
			$this->level = $level; #Store the log level

			$this->query = $query; #Store the query

			if(!($database->handler instanceof PDO))
				return $database->logger(LOG_ERR, 'Cannot prepare a statement on a non PDO object', 'Do not use a failed database object');

			try { $this->handler = $database->handler->prepare($query); } #Create prepared statement

			catch(PDOException $error)
			{
				$database->logger(LOG_ERR, "Query preparation failed for [$query] : \"".$error->getMessage().'"', 'Check the query and the error');
				$this->error = $error; #Keep the error object
			}
		}

		public function all() #Returns all the rows found
		{
			if(!$this->success) return array();

			$rows = $this->handler->fetchAll($this->_mode);
			return is_array($rows) ? $rows : array();
		}

		public function close() { return $this->handler->closeCursor(); } #Closes the unfinished (has not read all the results back) statement handler to start another query

		public function column() { return $this->success ? $this->handler->fetchColumn() : null; } #Returns the first value of the first row

		public function row() #Returns the next row found
		{
			if(!$this->success) return array();

		  	$row = $this->handler->fetch($this->_mode);
			return is_array($row) ? $row : array();
		}

		public function run($parameter = array()) #Executes the prepared statement
		{
			$this->database->log($this->query, $parameter, $this->level); #Log the query

			if(!($this->handler instanceof PDOStatement))
				return $this->database->logger(LOG_ERR, 'Cannot make a query on a non PDOStatement object', 'Do not make a query on a failed query object');

			try
			{
				$this->handler->execute($parameter);
				$this->success = true; #Query succeeded
			}

			catch(PDOException $error)
			{
				$this->database->logger(LOG_ERR, "Database query failed for [$this->query] : \"".$error->getMessage().'"', 'Check the error');
				$this->error = $error; #Keep the error object
			}
		}
	}
?>

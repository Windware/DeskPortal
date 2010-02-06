<?php
	class System_1_0_0
	{
		public $global, $self, $system;

		public function __construct($location)
		{
			$this->global = &System_Static::$global; #Link to the globally used variables

			#Set them temporarily for the log and user object to catch the system information (Redefined later)
			$this->system = array('id' => $class = get_class(), 'version' => preg_replace('/^[a-z]+_(\d+_\d+)$/i', '\1', $class));

			$this->global['define']['device'] = 'computer'; #FIXME

			$path['self'] = preg_replace('/\?.*/', '', $this->file_relative($location)); #Get the path to the requested file
			$path['system'] = $this->file_relative(__FILE__); #Get the path to this system

			$this->self = System_Static::app_env($path['self']); #Get path environments for the app
			$this->system = System_Static::app_env($path['system']); #Get path environments for the system

			$this->log(__METHOD__); #Report the creation of the system object
		}


		public function app_available($name) { return System_1_0_0_App::available($this, $name); }

		public function app_conf($name = null, $version = null, $key = null, $value = null)
		{
			return System_1_0_0_App::conf($this, $name, $version, $key, $value);
		}

		public function app_env($path) { return System_Static::app_env($path); }

		public function app_info($id) { return System_1_0_0_App::info($this, $id); }

		public function app_public($name) { return System_1_0_0_App::pub($this, $name); }

		public function app_path($name, $version = null) { return System_1_0_0_App::path($this, $name, $version); }

		public function app_version($name) { return System_1_0_0_App::version($this, $name); }


		public function cache_get($key, $compressed = false, $id = null)
		{
			if($id === null) $id = $this->self['id'];
			return System_Static::cache_operate($id, 'get', $key, null, $compressed);
		}

		public function cache_header($expire = null) { return System_1_0_0_Cache::header($this, $expire); }

		public function cache_modified($key, $compressed = false, $id = null)
		{
			if($id === null) $id = $this->self['id'];
			return System_Static::cache_operate($id, 'modified', $key, null, $compressed);
		}

		public function cache_set($key, $data, $compressed = false, $id = null)
		{
			if($id === null) $id = $this->self['id'];
			return System_Static::cache_operate($id, 'set', $key, $data, $compressed);
		}


		public function compress_build($string) { return System_1_0_0_Compress::build($this, $string); }

		public function compress_decode($string) { return System_1_0_0_Compress::decode($string); }

		public function compress_header() { return System_1_0_0_Compress::header($this); }

		public function compress_output($string) { return System_1_0_0_Compress::output($this, $string); }

		public function compress_possible() { return System_1_0_0_Compress::possible($this); }

		public function compress_ready() { return System_1_0_0_Compress::ready($this); }

		public function compress_requested() { return System_1_0_0_Compress::requested($this); }


		public function crypt_decrypt($data, $key) { return System_1_0_0_Crypt::decrypt($this, $data, $key); }

		public function crypt_encrypt($data, $key) { return System_1_0_0_Crypt::encrypt($this, $data, $key); }


		public function database($type, $caller, System_1_0_0_User $user = null, $app = null, $version = null, $reload = false)
		{
			static $_database; #Local database object cache
			$log = $this->log(__METHOD__);

			if($app === null) $app = $this->self['name'];
			if($version === null) $version = $this->self['db'];

			if("{$app}_$version" != 'system_static')
			{
				if(!$this->is_app($app)) return $log->param();
				if(!$this->is_digit($version)) return $log->param();
			}

			switch($type)
			{
				case 'user' : #If user is specified, open the user's database
					if($user === null) $user = $this->user();

					if(!$user->valid) #If opening user database when there is no valid user, quit
					{
						$problem = "No valid user found to open an user database '$app' version '$version'";
						return $log->dev(LOG_WARNING, $problem, 'Specify a valid user or make sure an user is logged in');
					}

					$id = "$app.$version.$user->id"; #Unique ID for cache index
				break;

				case 'system' : $id = "$app.$version"; break; #Open the system database if unspecified

				default :
					$problem = "Wrong database type specified opening database '$app' version '$version'";
					return $log->dev(LOG_ERR, $problem, 'Either specify user or system to open a database');
				break;
			}

			$log->dev(LOG_INFO, "Requesting using database '$app' version '$version'");

			if(!$reload && $_database[$id] instanceof System_1_0_0_Database) #If local cache exists, use it
			{
				$log->dev(LOG_INFO, "Using locally cached database connection for database '$app' version '$version'");
				$_database[$id]->caller = $this->file_relative($caller); #Switch the caller name TODO - Caller gets mixed if multiple app calls it at once

				return $_database[$id];
			}

			return $_database[$id] = new System_1_0_0_Database($this, $type, $caller, $user, $app, $version);
		}

		public function database_escape($string) { return System_1_0_0_Database::escape($string); }

		public function database_key($type, $key, $value = false, System_1_0_0_User $user = null, $app = null, $version = null)
		{
			return System_1_0_0_Database::key($this, $type, $key, $value, $user, $app, $version);
		}


		public function date_datetime($time = null) { return System_1_0_0_Date::datetime($this, $time); }


		public function event_run() { return System_1_0_0_Event::run($this); }


		public function file_conf($xml) { return System_Static::file_conf($xml); }

		public function file_load($file, $multiple = false, $severity = LOG_WARNING) { return System_Static::file_load($file, $multiple, $severity); }

		public function file_package($list, $compressed = true) { return System_1_0_0_File::package($this, $list, $compressed); }

		public function file_read($file, $severity = LOG_WARNING) { return System_1_0_0_File::read($this, $file, $severity); }

		public function file_readable($file) { return System_Static::file_readable($file); }

		public function file_relative($file) { return System_Static::file_relative($file); }

		public function file_store($file, $data) { return System_1_0_0_File::store($this, $file, $data); }

		public function file_type($file) { return System_1_0_0_File::type($this, $file); }


		public function folder_readable($folder) { return System_1_0_0_Folder::readable($folder); }


		public function image_background($param) { return System_1_0_0_Image::background($this, $param); }

		public function image_thumbnail($name, $header = false) { return System_1_0_0_Image::thumbnail($this, $name, $header); }


		public function is_app($name, $version = null) { return System_Static::is_app($name, $version); }

		public function is_address($subject) { return System_1_0_0_Is::address($subject); }

		public function is_color($color) { return System_1_0_0_Is::color($color); }

		public function is_digit($subject, $decimal = false, $negative = false) { return System_1_0_0_Is::digit($subject, $decimal, $nagative); }

		public function is_id($subject) { return System_Static::is_id($subject); }

		public function is_language($subject) { return System_1_0_0_Is::language($subject); }

		public function is_path($subject) { return System_Static::is_path($subject); }

		public function is_text($subject, $zero = false, $match = null) { return System_1_0_0_Is::text($subject, $zero, $match); }

		public function is_ticket($subject) { return System_1_0_0_Is::ticket($subject); }

		public function is_time($subject) { return System_1_0_0_Is::time($subject); }

		public function is_type($subject, $type) { return System_1_0_0_Is::type($subject, $type); }

		public function is_user($subject) { return System_Static::is_user($subject); }

		public function is_version($subject) { return System_Static::is_version($subject); }


		public function language_doc($id, $file, $language = null) { return System_1_0_0_Language::doc($this, $id, $file, $language); }

		public function language_file($id, $file, $language = null) { return System_1_0_0_Language::file($this, $id, $file, $language); }

		public function language_order($language = null) { return System_1_0_0_Language::order($this, $language); }


		public function log($caller)
		{
			static $_log; #Cached log objects specific to each functions
			if(!is_string($caller)) return false;

			$id = md5($caller); #Make it usable as a hash key

			if($_log[$id] instanceof System_1_0_0_Log)
			{
				$_log[$id]->caller = $caller; #Switch the caller name
				return $_log[$id];
			}

			return $_log[$id] = new System_1_0_0_Log($this, $caller);
		}

		public function log_query($mode = 2) { $this->app_conf('system', 'static', 'log_query', $mode); } #Debugging shortcut


		public function minify_css($code) { return System_1_0_0_Minify::css($this, $code); }

		public function minify_html($code) { return System_1_0_0_Minify::html($this, $code); }

		public function minify_js($code) { return System_1_0_0_Minify::js($this, $code); }


		public function network_http($request) { return System_1_0_0_Network::http($this, $request); }


		public function user($id = null, $reload = false)
		{
			static $_user; #Cached user objects

			$log = $this->log(__METHOD__);
			if($id !== null && !$this->is_digit($id)) return $log->param();

			if(!$reload && $_user[$id] instanceof System_1_0_0_User) return $_user[$id];
			return $_user[$id] = new System_1_0_0_User($this, $id);
		}

		public function user_create($name, $pass) { return System_1_0_0_User::create($this, $name, $pass); }

		public function user_find($name) { return System_1_0_0_User::find($this, $name); }


		public function xml_build($data, $exclude = array()) { return System_1_0_0_Xml::build($this, $data, $exclude); }

		public function xml_data($string) { return System_1_0_0_Xml::data($this, $string); }

		public function xml_dump($status, $name = null, $list = array(), $exclude = array(), $compress = false) { return System_1_0_0_Xml::dump($this, $status, $name, $list, $exclude, $compress); }

		public function xml_header($declare = true) { return System_1_0_0_Xml::header($this, $declare); }

		public function xml_fill($template, $values) { return System_1_0_0_Xml::fill($this, $template, $values); }

		public function xml_format($content, $declare = false) { return System_1_0_0_Xml::format($this, $content, $declare); }

		public function xml_node($name, $params, $child = null, $exclude = null) { return System_1_0_0_Xml::node($this, $name, $params, $child, $exclude); }

		public function xml_output($body, $compressed = false) { return System_1_0_0_Xml::output($this, $body, $compressed); }

		public function xml_send($status = false, $result = null, $key = null, $compressed = false) { return System_1_0_0_Xml::send($this, $status, $result, $key, $compressed); }

		public function xml_status($value, $key = null) { return System_1_0_0_Xml::status($this, $value, $key); }
	}
?>

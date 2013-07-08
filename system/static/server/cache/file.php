<?php
	class System_Static_Cache //Universal cache class
	{
		private static $_shape = '/^[a-z0-9_\/\-\.]+$/'; //Shape of a possible key

		private static $_conf = 'system/static/conf/cache/file.xml'; //Configuration file

		public function get($id, $key, $data = null, $compressed = false) //Retrieves the specified cache
		{
			static $_settings; //Configuration parameters cache
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_id($id) || !preg_match(self::$_shape, $key) || strstr('..', $key)) return $log->param();

			if(!is_array($_settings))
			{
				$_settings = System_Static::file_conf(self::$_conf); //Read its configuration
				if(!is_array($_settings)) return false;
			}

			//Construct the path to the cache file according to the application name and its version
			$item = $_settings['cache_path'].'/'.str_replace('_', '/', $id)."/$key";

			if($compressed) $item .= '.gz'; //If compressed file is requested, look for the compressed file
			return $system->file_read($item, LOG_NOTICE); //Send the file content
		}

		public function modified($id, $key) //Gets the last modified time for the cache
		{
		}

		public function set($id, $key, $data, $compressed = false) //Stores a cache on a file
		{
			static $_settings; //Configuration parameters cache
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_id($id) || !preg_match(self::$_shape, $key) || strstr('..', $key) || !is_string($data)) return $log->param();

			if(!is_array($_settings))
			{
				$_settings = System_Static::file_conf(self::$_conf); //Read its configuration
				if(!is_array($_settings)) return false;
			}

			//Construct the path to the cache file according to the application name and its version
			$item = $_settings['cache_path'].'/'.str_replace('_', '/', $id)."/$key";
			$log->dev(LOG_INFO, "Storing a file cache for '$key'");

			if(!$system->file_store($item, $data)) //Write the raw version of the cache
			{
				$solution = 'Check for the cache path file/directory permissions';
				return $log->dev(LOG_WARNING, "Cannot store a raw cache at '$item'", $solution);
			}

			if(!$compressed) return true; //Report success and quit if not compressing

			if(!$system->compress_ready()) //If zipping is not possible on the server side, quit
			{
				$solution = 'Consult the error from \'compress_ready\' function';
				return $log->dev(LOG_NOTICE, "Not possible to store compressed version for '$key'", $solution);
			}

			return $system->file_store("$item.gz", $system->compress_build($data)); //Write the zipped version of the cache
		}
	}

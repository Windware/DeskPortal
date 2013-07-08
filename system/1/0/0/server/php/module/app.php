<?php
	class System_1_0_0_App
	{
		public static function available(&$system, $name) //Return list of available versions for this app in XML
		{
			foreach(glob("$name/*/*/*/") as $revision) //Get all version numbers
				if(preg_match('|^.+?/(\d+)/(\d+)/\d+/$|', $revision, $matches)) $version[$matches[1]][$matches[2]]++; //Add amount of revisions

			foreach($version as $major => $minor) //For all available versions
			{
				$list = array('major' => $major);

				foreach($minor as $number => $revisions)
				{
					$list['minor'] = $number;
					$list['revisions'] = $revisions;

					$available[] = $list;
				}
			}

			return $available;
		}

		public static function conf(&$system, $name = null, $version = null, $key = null, $value = null) //Gets or sets application configuration
		{
			if($key !== null && !$system->is_text($key))
			{
				$log = $system->log(__METHOD__);
				return $log->param();
			}

			if($name === null && $version === null) //If not defined, use its own information
			{
				$name = $system->self['name'];
				$version = preg_replace('/^(\d+).+/', '\1', $system->self['version']);
			}

			return System_Static::app_conf($name, $version, $key, $value);
		}

		public static function info(&$system, $id) //Determines the name and the version of an application from an ID
		{
			if(!$system->is_id($id)) return false; //If not a valid ID string, quit
			$spec = explode('_', $id, 2); //Split the ID into 2

			return array('name' => $spec[0], 'version' => $spec[1]); //Return corresponding parts
		}

		public static function pub(&$system, $name) //Checks if an application is a publically available one
		{
			$log = $system->log(__METHOD__);
			if(!$system->is_text($name)) return $log->param();

			return in_array($name, $system->app_conf('system', 'static', 'app_public')); //Return if it's part of a public application
		}

		public static function path(&$system, $name, $version = null) //Find the root path of an application by giving application name and version
		{
			$log = $system->log(__METHOD__);

			if(!$system->is_app($name)) return $log->param();
			if($version !== null && !$system->is_version($version)) return $log->param();

			$base = "{$system->global['define']['top']}$name/"; //The top path for the application

			if($version === null) return $base; //If version is unspecified, return the application top absolute path
			return $base.str_replace('_', '/', $version).'/'; //Return the absolute path for that application version
		}

		public static function version(&$system, $name) //Picks the latest version of an application
		{
			$log = $system->log(__METHOD__);
			if(!$system->is_app($name)) return $log->param();
			
			$list = glob("{$system->global['define']['top']}$name/*", GLOB_ONLYDIR);
			$major = array_pop($list); //Pick the newest major number

			if($name === 'system') $major = array_pop($list); //Let go of the 'static' version for 'system'
			$major = preg_replace('|^.+/(\d+)$|', '\1', $major); //Pick only the number

			if(!$system->is_digit($major)) return false;

			$minor = array_pop(glob("{$system->global['define']['top']}$name/$major/*", GLOB_ONLYDIR)); //Pick the newest minor number
			$minor = preg_replace('|^.+/(\d+)$|', '\1', $minor); //Pick only the number

			if(!$system->is_digit($minor)) return false;

			$revision = array_pop(glob("{$system->global['define']['top']}$name/$major/$minor/*", GLOB_ONLYDIR)); //Pick the newest minor number
			$revision = preg_replace('|^.+/(\d+)$|', '\1', $revision); //Pick only the number

			if(!$system->is_digit($revision)) return false;
			return "{$major}_{$minor}_$revision"; //Return the version number
		}
	}

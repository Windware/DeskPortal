<?php
	class Search_1_0_0_Item
	{
		protected static $_limit = 20; #Results per page

		public static function limit($limit, $page) #Return SQL LIMIT clause with the given amount
		{
			$system = new System_1_0_0(__FILE__);

			if(!$system->is_digit($limit) || !$system->is_digit($page)) return '';
			return 'LIMIT '.(($page - 1) * $limit).", $limit";
		}

		public static function search($phrase, $area, $page, System_1_0_0_User $user = null) #Search for the phrase
		{ #TODO - Count the amount matched, send result in that order
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_text($phrase) || !is_array($area)) return $log->param();
			if(strlen($phrase) < 2) return $log->user(LOG_NOTICE, 'Search phrase must be 2 or more letters', 'Increase the search phrase length');

			#Get the list of supported apps from the shared file
			$supported = json_decode(file_get_contents("{$system->self['root']}resource/supported.json"), true);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			if(!$system->is_digit($page)) $page = 1; #Default to first page
			$all = array(); #Result sets

			foreach($area as $target)
			{
				list($app, $version) = explode('_', $target);
				if(!$system->is_app($app) || !$system->is_digit($version)) continue;

				if(!in_array($version, $supported[$app])) continue; #If not supported by this search app, forget it
				$module = "{$system->self['root']}server/php/resource/$target.php"; #The search mechanism module to load

				if(!$system->file_readable($module))
				{
					$log->dev(LOG_ERR, "Cannot load the search mechanism module for '$target'", 'Check for the file under server/php/resource');
					continue;
				}

				include_once($module); #Load the module
				$class = "{$system->self['id']}_Resource_".ucfirst($target); #The class name to call

				if(!class_exists($class)) #Don't let '__autoload' handle if the class is not found
				{
					$log->dev(LOG_ERR, "Required class is not declared in the search mechanism module for '$target'", 'Check the module file');
					continue;
				}

				$search = new $class($phrase, self::$_limit, $page, $user); #Initialize the search class with the given phrase (TODO - Cache the result for 5 minutes)
				if(!count($search->result)) continue; #If none found, drop it

				$nodes = array(); #Result fragments
				$index = 0; #Quit listing when it exceeds the limit

				foreach($search->result as $section => $list) #Create XML from the result
				{
					foreach($list as $result)
					{
						$nodes[] = array('name' => $section, 'attributes' => $result);
						if(++$index == self::$_limit) break 2;
					}
				}

				$amount = floor($search->count / self::$_limit);
				if($search->count % self::$_limit) $amount++;

				$all[] = array('attributes' => array('name' => $target, 'page' => $amount), 'child' => $nodes); #Wrap with app name node
			}

			return $all;
		}
	}
?>

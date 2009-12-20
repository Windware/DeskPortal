<?php
	class System_1_0_0_Language
	{
		public static function file(&$system, $app, $file, $language = null) #Find out the language file according to the preferred language
		{
			static $_preferred; #List of files to cache inside this function
			$log = $system->log(__METHOD__);

			if(!$system->is_id($app) || !is_string($file) || strstr('../', $file)) return $log->param();
			$log->dev(LOG_INFO, "Finding closest preferred language file for '$file' for application '$app'");

			#Cache the result with an unique ID
			$parameters = func_get_args(); #Function arguments (Assigned, since PHP refuses it to be used inside a function)
			array_shift($parameters); #Take the system parameter out

			$id = join('', $parameters); #Create an ID

			if(is_array($_preferred) && array_key_exists($id, $_preferred)) return $_preferred[$id]; #If already figured, use it
			$path = $system->global['define']['top'].str_replace('_', '/', $app).'/document/'; #The top path of the language folder

			#Find if any of the language file exists in the order of preference and return the path if found
			foreach($system->language_order($language) as $candidate) if($system->file_readable($document = "$path$candidate/$file")) return $files[$id] = $document;

			return false; #If no file is found, return false
		}

		#Find out the language preference order of an user
		#Preference order will be the passed value, user's configured value, browser configured value and English as a fallback
		public static function order(&$system, $language = null, System_1_0_0_User $user = null)
		{
			static $_list; #A variable to remember the order calculation
			$log = $system->log(__METHOD__);

			if(is_array($_list)) $order = $_list; #If already calculated, use it instead
			else
			{
				$log->dev(LOG_INFO, 'Finding language preference order');
				if($user === null) $user = $system->user();

				$order = array(); #List of languages
				$browser = array(); #Browser configured languages

				if($user->valid) #If user has set a language preference, add it to the list
				{
					$database = $system->database('user', __METHOD__, $user, 'system', 'static');

					if($database->success)
					{
						$query = $database->prepare("SELECT value FROM {$database->prefix}conf WHERE user = :user AND app = :app AND key = :key");
						$query->run(array(':user' => $user->id, ':app' => 'system_static', ':key' => 'language'));

						if($query->success && $system->is_language($choice = $query->column())) array_push($order, $choice);
					}
				}

				#Find the browser's language preference and list them up
				foreach(explode(',', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'])) as $configured)
				{
					#Get the priority value and put the langauge preference in the order
					$used = explode(';', $configured); #Split the preference value
					$priority = preg_match('/^q=([\d\.]+)$/', $used[1], $matches) ? $matches[1] : 1; #Find the preference value

					$browser[$used[0]] = $priority; #Set the language's preference

					#Add major language classification as the next priority against country based language classification. Ex : Put 'en' behind 'en_GB'
					$base = explode('-', $used[0]); #Separate the major language classification to country based classification
					if(count($base) == 2 && !array_key_exists($base[0], $browser)) $browser[$base[0]] = $priority - 0.1;
				}

				#If English does not exist as a preference, add it as the last priority as a fallback language
				if(!array_key_exists('en', $browser)) $browser['en'] = -1;

				arsort($browser); #Sort it by the priority value

				#Add the browser preference after the user's preference
				foreach(array_keys($browser) as $used) if(!in_array($used, $order)) array_push($order, $used);
				$_list = $order; #Keep it remembered for next use
			}

			if($language === null) $language = $_SERVER['QUERY_STRING']; #Use the query string

			if($system->is_language($language)) #If specified parameter is a valid language string
			{
				if(strstr($language, '-')) array_unshift($order, strtolower(array_shift(explode('-', $language))));
				array_unshift($order, strtolower($language)); #Add the langauge given to the foremost

				$order = array_unique($order); #Crop any duplicates
			}

			return $order; #Return the preferred language order
		}
	}
?>

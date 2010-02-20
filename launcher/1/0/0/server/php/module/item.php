<?php
	class Launcher_1_0_0_Item
	{
		public static function get($language, System_1_0_0_User $user = null)
		{
			$system = new System_1_0_0(__FILE__);
			$xml = '';

			if($user === null) $user = $system->user();
			if(!$user->valid) return $xml;

			#Load the category name XML file
			$names = simplexml_load_file($system->language_file($system->system['id'], 'categories.xml', $language));
			if(!$names) return $xml;

			$localized = array(); #List of localized category names
			foreach($names->string as $entry) $localized[(string) $entry['name']] = (string) $entry['value']; #Put them in a hash

			$database = $system->database('system', __METHOD__, null, 'system', 'static');
			if(!$database->success) return $xml;

			$query = $database->prepare("SELECT app FROM {$database->prefix}subscription WHERE user = :user AND (invalid = :invalid OR invalid IS NULL) AND app != :system");
			$query->run(array(':user' => $user->id, ':invalid' => 0, ':system' => 'system'));

			if(!$query->success) return $xml;

			foreach($query->all() as $row) $subscribed[] = $row['app']; #Get the list of all subscribed applications

			$conf = $system->app_conf('system', 'static');
			$subscribed = array_unique(array_merge($subscribed, $conf['app_public'])); #Merge what's available for the user

			if(!count($subscribed)) return $xml;

			$database = $system->database('user', __METHOD__, $user, 'system', 'static');
			if(!$database->success) return $xml;

			$query = $database->prepare("SELECT app, version FROM {$database->prefix}used WHERE User = :user");
			$query->run(array(':user' => $user->id));

			if(!$query->success) return $xml;

			$display = array(); #List of titles to display
			$list = array(); #List of version numbers the user prefers
			$icon = array(); #List to hold the icon's presence

			$versions = array(); #List of version numbers the user prefers
			foreach($query->all() as $row) $versions[$row['app']] = $row['version'];

			$conf = array(); #User configurations
			foreach($user->conf('conf') as $row) if($system->is_id($row['app'])) $conf[$row['app']][$row['name']] = $row['value'];

			foreach($subscribed as $name)
			{
				$used = $system->is_version($versions[$name]) ? $versions[$name] : $system->app_version($name);
				$id = "{$name}_$used"; #ID of the application

				if(!$system->is_id($id)) continue;
				$path = str_replace('_', '/', $id); #Get the meta description file

				$xml = "{$system->global['define']['top']}$path/meta.xml"; #The meta information XML
				if(!$system->file_readable($xml)) continue;

				$meta = simplexml_load_file($xml); #Load meta information

				foreach($meta->info as $entry) #Categorize the application in the array
				{
					$category = (string) $entry['category'];
					if(!$category) $category = 'uncategorized';

					$list[$category][] = $id; #Add to the list
				}

				$theme = $conf[$id]['theme'] ? $conf[$id]['theme'] : "$path/component/default/";
				$graphic = "$theme{$system->global['define']['device']}/graphic/icon.png"; #Load the icon on the current theme

				$icon[$id] = $system->file_readable($graphic) ? $graphic : ''; #Note the icon's theme
			}

			$apps = array();

			foreach($list as $category => $entry) #Build up the list of apps
			{
				$show = $localized[$category] ? $localized[$category] : $category;
				$each = array();

				foreach($entry as $launcher) $each[] = array('name' => $launcher, 'icon' => $icon[$launcher]);

				$state = $system->database_key('user', "opened_$category", null, $user);
				$apps[] = array('attributes' => array('name' => $category, 'display' => $show, 'expand' => $state), 'entry' => $each);
			}

			return $apps;
		}

		public static function expand($category, $state, System_1_0_0_User $user = null) #Set opened category
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!preg_match('/^\w+$/', $category)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			return $system->database_key('user', "opened_$category", $state ? 1 : 0, $user);
		}
	}
?>

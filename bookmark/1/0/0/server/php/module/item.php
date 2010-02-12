<?php
	class Bookmark_1_0_0_Item
	{
		protected static function _count($user, $id) #Update the address reference state from the user
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$user->valid || !$system->is_digit($id)) return $log->param();

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT count(id) FROM {$database->prefix}bookmark WHERE user = :user AND id = :id");
			$query->run(array(':user' => $user->id, ':id' => $id));

			if(!$query->success) return false;
			$referred = $query->column(); #Get the reference state

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			if($referred) #If reference exists, keep the count
			{
				$query = $database->prepare("REPLACE INTO {$database->prefix}reference (user, address, referred) VALUES (:user, :address, :referred)");
				$query->run(array(':referred' => $referred, ':user' => $user->id, ':address' => $id));
			}
			else #Otherwise delete the record
			{
				$query = $database->prepare("DELETE FROM {$database->prefix}reference WHERE user = :user AND address = :address");
				$query->run(array(':user' => $user->id, ':address' => $id));
			}

			return $query->success;
		}

		public static function add($address, System_1_0_0_User $user = null) #Add a new address
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_address($address)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT id, title, type FROM {$database->prefix}address WHERE address = :address");
			$query->run(array(':address' => $address));

			if(!$query->success) return false;
			$row = $query->row();

			if(!count($row) || (preg_match('|^text/html\b|i', $row['type']) || !$row['type']) && !strlen($row['title'])) #If not registered in the system address list or title was never entered
			{
				#Get the type of the resource with a 'HEAD" request first
				$request = $system->network_http(array(array('address' => $address, 'method' => 'HEAD', 'max' => Bookmark_1_0_0_Cache::$_max * 1000)));

				if(preg_match('|^text/html\b|i', $request[0]['header']['content-type'])) #If HTML, get the title of the page
				{
					$request = $system->network_http(array(array('address' => $address, 'max' => Bookmark_1_0_0_Cache::$_max * 1000)));
					preg_match('|<\s*title[^>]*>([^<]+)<\s*/\s*title\s*>|i', $request[0]['body'], $match); #Find out the title of the page

					$title = trim($match[1]);
					$title = mb_convert_encoding($title, 'utf-8', mb_detect_encoding($title));
				}

				if(!strlen($title)) $title = $address; #If no title is set, set it to its address

				if(!count($row)) #If the address does not exist, insert a new record
				{
					$query = $database->prepare("INSERT INTO {$database->prefix}address (address, title, type) VALUES (:address, :title, :type)");
					$query->run(array(':address' => $address, ':title' => $title, ':type' => $request[0]['header']['content-type'])); #Add it

					$id = $database->id();
				}
				else #If the record exists with empty title, update the title
				{
					$query = $database->prepare("UPDATE {$database->prefix}address SET title = :title, type = :type WHERE id = :id");
					$query->run(array(':title' => $title, ':type' => $request[0]['header']['content-type'], ':id' => $row['id']));

					$id = $row['id'];
				}

				if(!$query->success) return false;
				$row = array('id' => $id, 'title' => $title); #Get the inserted ID for the address field
			}

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT COUNT(id) FROM {$database->prefix}bookmark WHERE user = :user AND id = :id");
			$query->run(array(':user' => $user->id, ':id' => $row['id']));

			if(!$query->success) return false;

			if(!$query->column()) #If not registered in the user's bookmark list, add it
			{
				$query = $database->prepare("INSERT INTO {$database->prefix}bookmark (user, id, added, name) VALUES (:user, :id, :added, :name)");
				$query->run(array(':user' => $user->id, ':id' => $row['id'], ':added' => $system->date_datetime(), ':name' => $row['title']));
			}
			else return null; #Report duplicate addition

			self::_count($user, $row['id']); #Update the reference counter
			return $query->success ? $row['id'] : false;
		}

		public static function get($cat = array(), System_1_0_0_User $user = null) #List bookmarks
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!is_array($cat)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			switch($_GET['order'])
			{
				case 0:
					$order = ' ORDER BY name';
				break;

				case 3:
					$order = ' ORDER BY added DESC';
				break;

				case 4:
					$order = ' ORDER BY viewed DESC';
				break;
			}

			$filter = array(); #Filter query string
			$values = array(':user' => $user->id); #Query values

			foreach($cat as $index => $category) #Create a list of categories to be appended on the query
			{
				$filter[] = ":i{$index}d";
				$values[":i{$index}d"] = $category;
			}

			if(count($filter)) $filter = ' AND rl.category IN ('.implode(',', $filter).')'; #Concatenate the query
			else $filter = '';

			$statement = "SELECT bm.id, count(rl.bookmark) as count, bm.added, bm.name, bm.viewed FROM {$database->prefix}bookmark as bm LEFT JOIN
			{$database->prefix}relation as rl ON bm.id = rl.bookmark WHERE bm.user = :user$filter GROUP BY id$order";

			$query = $database->prepare($statement);
			$query->run($values);

			if(!$query->success) return false;

			$count = count($_GET['cat']); #Category count
			$results = $query->all();

			#Get list of categories that this bookmark belongs to
			$relation = $database->prepare("SELECT category FROM {$database->prefix}relation WHERE user = :user AND bookmark = :bookmark");

			#Get the time the cache was taken
			$cache = $database->prepare("SELECT time FROM {$database->prefix}cache WHERE user = :user AND bookmark = :bookmark AND file = :file");

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT * FROM {$database->prefix}address WHERE id = :id"); #Get the address information : TODO - Could use 'OR' on 'id' as a single query
			$list = array(); #Bookmark XML lines

			foreach($results as $row)
			{
				if($row['count'] != $count) continue; #If the bookmark is not included in all categories, leave it

				$query->run(array(':id' => $row['id']));
				if(!$query->success) return false; #Return empty upon an error in the middle

				$relation->run(array(':user' => $user->id, ':bookmark' => $row['id']));
				if(!$relation->success) return false;

				$category = array();
				foreach($relation->all() as $result) $category[] = $result['category'];

				$info = array('category' => implode(',', $category));
				foreach($query->row() as $key => $value) $info[$key] = $value; #Concatenate the address information

				$cache->run(array(':user' => $user->id, ':bookmark' => $row['id'], ':file' => $info['address']));
				if(!$cache->success) return false;

				$info['cache'] = $cache->column(); #Add the time the cache was acquired

				foreach(array('added', 'name', 'viewed') as $section) $info[$section] = $row[$section];
				$list[$info['address']] = $info;
			}

			if($_GET['order'] == 1) ksort($list); #Sort by address if specified so
			return $list;
		}

		public static function import($file) #Import bookmark files
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_text($file)) return $log->param();

			$data = $system->file_read($file);
			if(!strlen($data = trim($data))) return false;

			if(preg_match('/^<!DOCTYPE NETSCAPE-Bookmark-file-1>/i', $data)) $type = 'netscape'; #If in a common netscape HTML format
			else return $log->user(LOG_WARNING, 'Bookmark file format not recognizable', 'Send the bookmark in a format that can be parsed');

			$data = mb_convert_encoding($data, 'utf-8', mb_detect_encoding($data)); #Make sure it is in UTF8
			$encoding = mb_detect_encoding($data);

			if($encoding != 'UTF-8' && $encoding != 'US-ASCII') return false; #If cannot be converted, quit to avoid corrupting output XML
			if(!$system->file_load("{$system->self['root']}server/php/resource/$type.php")) return false; #Load the external parser file

			$class = 'Bookmark_1_0_0_Resource_'.ucfirst($type); #Do not allow __autoload to handle loading the class if missing
			if(!class_exists($class)) return $log->dev(LOG_ERR, "Parser file '$type.php' does not have the class to be used : $class", 'Check the parser file');

			$parser = new $class($data); #Parse and store
			if(!$parser->loaded) return $log->dev(LOG_ERR, "Failed to load bookmark content for type '$type'", 'Check for the bookmark file structure');

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = array('user' => $system->database('user', __METHOD__, $user));
			if(!$database['user']->success) return false;

			$database['system'] = $system->database('system', __METHOD__);
			if(!$database['system']->success) return false;

			$query = array('select' => $database['user']->prepare("SELECT id FROM {$database['user']->prefix}category WHERE user = :user AND name = :name"));
			$query['insert'] = $database['user']->prepare("INSERT INTO {$database['user']->prefix}category (user, name) VALUES (:user, :name)");

			$all = $name = $relation = array();

			foreach($parser->list as $category => $link) #Check for category existence
			{
				if(strlen($category))
				{
					$query['select']->run(array(':user' => $user->id, ':name' => $category));
					if(!$query['select']->success) return false;

					$id = $query['select']->column(); #Keep the name and ID relation for category

					if(!$id)
					{
						$query['insert']->run(array(':user' => $user->id, ':name' => $category)); #Save the new category
						if(!$query['insert']->success) return false;

						$id = $database['user']->id(); #Store the ID
					}
				}
				else $id = null;

				foreach($link as $data)
				{
					if(!$system->is_address($data['address'])) continue;

					$all[] = $data['address']; #List all up on a flat array
					$name[$data['address']] = $data['name']; #Keep name and address relation for address

					if($id) $relation[$data['address']][] = $id; #Keep which bookmark belongs into which category
				}
			}

			$new = $name;
			$time = $system->date_datetime();

			$query = array('add' => $database['system']->prepare("INSERT INTO {$database['system']->prefix}address (address) VALUES (:address)"));

			$query['insert'] = $database['user']->prepare("INSERT INTO {$database['user']->prefix}bookmark (user, id, added, name) VALUES (:user, :id, :added, :name)");
			$query['category'] = $database['user']->prepare("INSERT INTO {$database['user']->prefix}relation (user, bookmark, category) VALUES (:user, :bookmark, :category)");

			for($i = 0; $i < count($all); $i += $database['system']->limit)
			{
				$param = $target = array();

				foreach(array_slice($all, $i, $database['system']->limit) as $index => $address)
				{
					$value = $target[] = ":i{$index}d";
					$param[$value] = $address;
				}

				if(!count($target)) continue;
				$target = implode(', ', $target);

				$query['select'] = $database['system']->prepare("SELECT id, address, title FROM {$database['system']->prefix}address WHERE address IN ($target)");
				$query['select']->run($param); #Get registered addresses in the system database

				if(!$query['select']->success) return false;

				$exist = $info = $list = $target = array();
				$param = array(':user' => $user->id);

				foreach($query['select']->all() as $index => $row)
				{
					$value = $target[] = ":i{$index}d";
					$param[$value] = $row['id'];

					$list[] = $row['id']; #Bookmark ID existing in system database
					$info[$row['id']] = $row; #Keep the bookmark information

					if($relation[$row['address']])
					{
						$relation[$row['id']] = $relation[$row['address']]; #Convert the bookmark address into ID
						unset($relation[$row['address']]);
					}

					unset($new[$row['address']]); #Filter out existing addresses to find out the missing addresses
				}

				if(!count($target)) continue;
				$target = implode(', ', $target);

				$query['user'] = $database['user']->prepare("SELECT id FROM {$database['user']->prefix}bookmark WHERE user = :user AND id IN ($target)");
				$query['user']->run($param); #Find which addresses are registered in the user's database

				if(!$query['user']->success) return false;
				foreach($query['user']->all() as $row) $exist[] = $row['id']; #Bookmark ID existing in user database

				foreach(array_diff($list, $exist) as $id) #Insert bookmark not in the user database
				{
					if(!$database['user']->begin()) return false;

					$query['insert']->run(array(':user' => $user->id, ':id' => $id, ':added' => $time, ':name' => $name[$info[$id]['address']]));
					if(!$query['insert']->success) return false;

					foreach(array_unique($relation[$id]) as $category)
					{
						$query['category']->run(array(':user' => $user->id, ':bookmark' => $id, ':category' => $category)); #Link bookmark into categories
						if(!$query['category']->success) return false;
					}

					if(!$database['user']->commit()) return $database['user']->rollback() && false;
					self::_count($user, $id); #Update the reference counter
				}
			}

			foreach($new as $address => $name) #For new adddesses
			{
				$query['add']->run(array(':address' => $address)); #Insert into system database
				if(!$query['add']->success) return false;

				$id = $database['system']->id(); #Get the ID of the bookmark
				if(!$id || !$database['user']->begin()) return false;

				$query['insert']->run(array(':user' => $user->id, ':id' => $id, ':added' => $time, ':name' => $name)); #Insert into user database
				if(!$query['insert']->success) return false;

				foreach(array_unique($relation[$address]) as $category)
				{
					$query['category']->run(array(':user' => $user->id, ':bookmark' => $id, ':category' => $category)); #Link bookmark into categories
					if(!$query['category']->success) return false;
				}

				if(!$database['user']->commit()) return $database['user']->rollback() && false;
				self::_count($user, $id); #Update the reference counter
			}

			return true;
		}

		public static function remove($id, System_1_0_0_User $user = null) #Remove a bookmark
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("DELETE FROM {$database->prefix}relation WHERE user = :user AND bookmark = :bookmark");
			$query->run(array(':user' => $user->id, ':bookmark' => $id)); #Remove the category relation

			if(!$query->success) return false;

			$query = $database->prepare("DELETE FROM {$database->prefix}bookmark WHERE user = :user AND id = :id");
			$query->run(array(':user' => $user->id, ':id' => $id)); #Remove the bookmark entry

			if(!$query->success) return false;
			self::_count($user, $id); #Update the reference counter

			return true;
		}

		public static function set($address, $name, $categories, $id, System_1_0_0_User $user = null) #Sets a bookmark's data
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!is_array($categories)) $categories = array();
			if(!$system->is_digit($id) || !$system->is_address($address) || !$system->is_text($name) || !is_array($categories)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT address FROM {$database->prefix}address WHERE id = :id");
			$query->run(array(':id' => $id));

			if(!$query->success) return false;
			$url = $query->column(); #Get the address of the updating bookmark

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT * FROM {$database->prefix}bookmark WHERE user = :user AND id = :id");
			$query->run(array(':user' => $user->id, ':id' => $id));

			if(!$query->success) return false;
			$result = $query->row(); #Get the bookmark info

			$query = $database->prepare("DELETE FROM {$database->prefix}relation WHERE user = :user AND bookmark = :bookmark");
			$query->run(array(':user' => $user->id, ':bookmark' => $id)); #Delete the old category and bookmark relation

			if(!$query->success) return false;

			if($url != $address) #If address changed
			{
				$query = $database->prepare("DELETE FROM {$database->prefix}bookmark WHERE user = :user AND id = :id");
				$query->run(array(':user' => $user->id, ':id' => $id)); #Delete the old bookmark

				if(!$query->success) return false;
				self::_count($user, $id); #Update the reference counter

				$id = self::add($address, $user); #Register as new
				if(!$system->is_digit($id)) return false;

				#Restore the information from the old bookmark entry
				$query = $database->prepare("UPDATE {$database->prefix}bookmark SET name = :name, added = :added, viewed = :viewed WHERE user = :user AND id = :id");
				$query->run(array(':name' => $name, ':added' => $result['added'], ':viewed' => $result['viewed'], ':user' => $user->id, ':id' => $id));

				if(!$query->success) return false;
			}
			elseif($name != $result['name']) #When address is kept intact and the name has changed
			{
				$query = $database->prepare("UPDATE {$database->prefix}bookmark SET name = :name WHERE user = :user AND id = :id");
				$query->run(array(':name' => $name, ':user' => $user->id, ':id' => $id)); #Update its name

				if(!$query->success) return false;
			}

			if(!$query->success) return false;
			$success = true;

			if(count($categories))
			{
				if(!$database->begin()) return false; #Start a transaction to make multiple category relation atomic
				$query = $database->prepare("INSERT INTO {$database->prefix}relation (user, bookmark, category) VALUES (:user, :bookmark, :category)");

				foreach($categories as $cat) #For all of the provided categories
				{
					if(!$system->is_digit($cat)) continue;

					$query->run(array(':user' => $user->id, ':bookmark' => $id, ':category' => $cat)); #Relate it to the bookmark
					if($query->success) continue;

					$database->rollback(); #On failure, rollback
					$success = false; #Report failure on this procedure

					break; #Quit trying
				}

				if($success) $success = $database->commit() || $database->rollback() && false;
			}

			self::_count($user, $id); #Update the reference counter
			return $success; #Do not bother reference counting errors if it happens
		}

		public static function viewed($id, System_1_0_0_User $user = null) #Increase the view count of a page
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT viewed FROM {$database->prefix}bookmark WHERE user = :user AND id = :id");
			$query->run(array(':user' => $user->id, ':id' => $id));

			if(!$query->success) return false;
			$viewed = $query->column() + 1; #Get the current view count

			$query = $database->prepare("UPDATE {$database->prefix}bookmark SET viewed = :viewed WHERE user = :user AND id = :id");
			$query->run(array(':viewed' => $viewed, ':user' => $user->id, ':id' => $id));

			return $query->success;
		}
	}
?>

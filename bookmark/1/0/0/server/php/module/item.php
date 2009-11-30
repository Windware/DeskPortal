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

			$query = $database->prepare("SELECT id FROM {$database->prefix}address WHERE address = :address");

			$query->run(array(':address' => $address));
			if(!$query->success) return false;

			if(!$system->is_digit($id = $query->column())) #If not registered in the system address list
			{
				$query = $database->prepare("INSERT INTO {$database->prefix}address (address) VALUES (:address)");
				$query->run(array(':address' => $address)); #Add it

				if(!$query->success) return false;
				$id = $database->id(); #Get the inserted ID for the address field
			}

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT COUNT(id) FROM {$database->prefix}bookmark WHERE user = :user AND id = :id");
			$query->run(array(':user' => $user->id, ':id' => $id));

			if(!$query->success) return false;

			if(!$query->column()) #If not registered in the user's bookmark list, add it
			{
				$query = $database->prepare("INSERT INTO {$database->prefix}bookmark (user, id, added) VALUES (:user, :id, :added)");
				$query->run(array(':user' => $user->id, ':id' => $id, ':added' => $system->date_datetime()));
			}

			self::_count($user, $id); #Update the reference counter
			return $query->success ? $id : false;
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
				$filter[] = "rl.category = :category{$index}_index";
				$values[":category{$index}_index"] = $category;
			}

			if(count($filter)) $filter = ' AND ('.implode(' OR ', $filter).')'; #Concatenate the query
			else $filter = '';

			$statement = "SELECT bm.id, count(rl.bookmark), bm.added, bm.name, bm.viewed FROM {$database->prefix}bookmark as bm LEFT JOIN {$database->prefix}relation as rl
			ON rl.user = :user$filter AND bm.user = :user AND bm.id = rl.bookmark GROUP BY id$order";

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
				if($row['count(rl.bookmark)'] != $count) continue; #If the bookmark is not included in all categories, leave it

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

				foreach(explode(' ', 'added name viewed') as $section) $info[$section] = $row[$section];
				$list[$info['address']] = $system->xml_node('bookmark', $info);
			}

			if($_GET['order'] == 1) ksort($list); #Sort by address if specified so
			return implode('', $list);
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
			if(!$system->is_digit($id) || !$system->is_address($address) || !is_string($name) || !is_array($categories)) return $log->param();

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
				$database->begin(); #Start a transaction to make multiple category relation atomic
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

				if($success) $database->commit(); #Commit the sequence
			}

			self::_count($user, $id); #Update the reference counter
			return $success; #Do not bother reference counting errors if it happens
		}

		public static function viewed($address, System_1_0_0_User $user = null) #Increase the view count of a page
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_address($address)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT id FROM {$database->prefix}address WHERE address = :address");
			$query->run(array(':address' => $address));

			if(!$query->success) return false;
			$id = $query->column();

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

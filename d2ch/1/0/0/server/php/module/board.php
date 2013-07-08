<?php
	class D2ch_1_0_0_Board
	{
		protected static $_max = 200; #Max KB to get from the board list page

		protected static $_source = 'http://menu.2ch.net/bbsmenu.html'; #List of boards

		protected static $_wait = 24; #Hours to wait till refreshing the list of boards

		public static function get() #Get list of boards
		{
			$system = new System_1_0_0(__FILE__);

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			$updated = $system->database_key('system', 'updated');
			if(!$updated || strtotime($updated) + self::$_wait * 3600 < time()) self::update(); #Update the listing if old

			$query = $database->prepare("SELECT id, name, category FROM {$database->prefix}board ORDER BY id");
			if(!$query->run()) return false;

			foreach($query->all() as $row) $list[$row['id']] = $row;
			return $list;
		}

		public static function update() #Update list of boards
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT * FROM {$database->prefix}board");
			$query->run();

			if(!$query->success) return false;
			foreach($query->all() as $row) $board[$row['address']] = $row;

			$source = $system->network_http(array(array('address' => self::$_source, 'max' => self::$_max * 1000)));
			if($source[0]['status'] != 200) return false;

			$collection = array(); #List of boards found

			foreach(explode("\n", mb_convert_encoding($source[0]['body'], 'utf-8', mb_detect_encoding($source[0]['body']))) as $line)
			{
				if(preg_match('|<BR><BR><B>(.+?)</B><BR>|i', $line, $match)) $category = $match[1];
				elseif($category && preg_match('|<A HREF=(.+?)>(.+?)</A>|i', $line, $match)) $collection[$category][] = array('name' => $match[2], 'address' => str_replace('"', '', $match[1]));
			}

			$query = $database->prepare("SELECT * FROM {$database->prefix}category");
			if(!$query->run()) return false;

			$relation = array(); #Category name and ID relation
			foreach($query->all() as $row) $relation[$row['name']] = $row['id'];

			if(!$database->begin()) return false;

			$query = $database->prepare("DELETE FROM {$database->prefix}category WHERE id = :id"); #Remove non existing categories
			foreach(array_diff(array_keys($relation), array_keys($collection)) as $category) if(!$query->run(array(':id' => $relation[$category]))) return false;

			$query = $database->prepare("INSERT INTO {$database->prefix}category (name) VALUES (:name)");

			foreach(array_diff(array_keys($collection), array_keys($relation)) as $category) #Add existing categories
			{
				if(!$query->run(array(':name' => $category))) return $database->rollback() && false;
				$relation[$category] = $database->id(); #Keep category ID
			}

			$query = $database->prepare("SELECT * FROM {$database->prefix}board");
			if(!$query->run()) return false;

			$exist = array();

			foreach($query->all() as $row) $exist[$row['name']][] = $row['category']; #Currently existing boards
			$query = $database->prepare("INSERT INTO {$database->prefix}board (name, address, category, updated) VALUES (:name, :address, :category, :updated)");

			foreach($collection as $category => $list) #For all the categories of boards fetched remotely
			{
				foreach($list as $board) #For each board
				{
					if(is_array($exist[$board['name']]) && in_array($relation[$category], $exist[$board['name']])) continue; #If it is registered, go next

					$success = $query->run(array(':name' => $board['name'], ':address' => $board['address'], ':category' => $relation[$category], ':updated' => null));
					if(!$success) return $database->rollback() && false;
				}
			}

			if(!$database->commit()) return $database->rollback() && false;
			return $system->database_key('system', 'updated', $system->date_datetime());
		}
	}
?>

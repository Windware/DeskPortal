<?php
	class Headline_1_0_0_Entry
	{
		protected static $_limit = 20; #Entries to display per page

		protected static function _date(&$system, $year, $month, $day = 0) #Create a date string with padding
		{
			$log = $system->log(__METHOD__);
			if(!$system->is_digit($year) || !$system->is_digit($month) || !$system->is_digit($day)) return $log->param();

			if($month < 10) $month = '0'.((int) $month); #Pad month
			if(!$day) return implode('-', array($year, $month)); #For month specifying, return without the day

			if($day < 10) $day = '0'.((int) $day); #Pad day
			return implode('-', array($year, $month, $day)); #Add the day and return
		}

		public static function get($param, System_1_0_0_User $user = null) #List of a feed's entries
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			$id = $param['feed']; #Keep the feed ID
			if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			$base = array(':feed' => $id); #Query parameters
			$values = array(); #Array of values to merge when executing queries

			if(is_string($param['category']) && $param['category']) #Filter by category
			{
				$limiter .= ' AND category = :category';
				$values[':category'] = $param['category'];
			}

			if($param['marked'] == '1') #Filter by marked entry
			{
				$limiter .= ' AND rate = :rate';
				$values[':rate'] = 5;
			}

			if(strlen($param['search'])) #Filter by search words
			{
				$search = ' AND (subject LIKE :search OR description LIKE :search)';
				$base[':search'] = '%'.$system->database_escape($param['search']).'%';
			}

			if($param['unread'] == '1' && $limiter) #Filter by unread status
			{
				#If filtering with other values, simply pick the entries those are not flagged 'read',
				#otherwise drop everything those are flagged as 'read'
				$limiter .= ' AND read != :read';
				$values[':read'] = 1;
			}

			switch((int) $param['period']) #According to the display span settings
			{
				case 0 : #Daily span
					$search .= ' AND date LIKE :date';
					$base[':date'] = self::_date($system, $param['year'], $param['month'], $param['day']).' %';
				break;

				case 1 : #Weekly span
					$search .= ' AND date >= :start AND date <= :end';
					$max = date('t', mktime(0, 0, 0, $param['month'], 1, $param['year'])); #Get max day of the month

					$first = 1 + ($param['week'] - 1) * 7; #Get the starting day of the specified week
					if($first > $max) $first = $max; #If hitting the next month, set it as the last day

					$last = $param['week'] * 7; #Get the day of the week next
					if($last > $max) $last = $max; #If hitting the next month, set it as the last day

					$base[':start'] = self::_date($system, $param['year'], $param['month'], $first).' 00:00:00';
					$base[':end'] = self::_date($system, $param['year'], $param['month'], $last).' 23:59:59';
				break;

				case 2 : #Monthly span
					$search .= ' AND date LIKE :date';
					$base[':date'] = self::_date($system, $param['year'], $param['month']).'-%';
				break;

				default :
					$query = $database->prepare("SELECT Max(date) FROM {$database->prefix}entry WHERE feed = :feed");
					$query->run(array(':feed' => $id));

					if(!$query->success) return false;

					$search .= ' AND date LIKE :date';
					$base[':date'] = preg_replace('/ .+/', '', $query->column()).' %'; #Get the most recent day on the feed
				break;
			}

			$query = array(); #List of database queries
			$query['detail'] = $database->prepare("SELECT * FROM {$database->prefix}entry WHERE id = :id");

			$query['system'] = $database->prepare("SELECT id FROM {$database->prefix}entry WHERE feed = :feed$search ORDER BY date DESC");
			$query['system']->run($base); #Get the list of entries

			$entries = $query['system']->all();
			if(!$query['system']->success) return false;

			if(count($entries) == 0) return true; #Quit if no entries are found

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			#Get the user's preference on the entry
			$query['user'] = $database->prepare("SELECT entry, category, rate, read FROM {$database->prefix}entry WHERE user = :user AND entry = :entry$limiter");

			#Position of entries to return back
			$start = ($param['page'] - 1) * self::$_limit + 1;
			$end = $start + self::$_limit;

			foreach($entries as $row) #Against all entries those matched in the query
			{
				$query['user']->run(array_merge(array(':user' => $user->id, ':entry' => $row['id']), $values)); #Get the entry info
				if(!$query['user']->success) return false; #If a query fails, do not try anymore

				$pref = $query['user']->row(); #Get the single result row of user's preference

				#If looking for every unread entries, drop anything with 'read' flag on
				if($param['unread'] == 1 && !count($values)) { if($pref['read'] == 1) continue; }
				#Otherwise, pick the entry with filter properties set and 'read' flag as off
				elseif($limiter && !$pref) continue;

				if($param['category'] == '0' && $pref['category']) continue;
				if(++$amount < $start || $amount >= $end) continue; #If not within the displaying page, drop

				$parts = array(); #XML attributes
				$query['detail']->run(array(':id' => $row['id'])); #Get the entry's detail

				if(!$query['detail']->success) return false;
				$item = $query['detail']->row(); #Entry information

				foreach(explode(' ', 'id link date subject') as $entry) $parts[$entry] = preg_replace('/<.+?>/', ' ', $item[$entry]);
				foreach(explode(' ', 'category rate read') as $entry) $parts[$entry] = $pref[$entry];

				$xml .= $system->xml_node('entry', $parts, $system->xml_data(preg_replace('/<.+?>/', ' ', $item['description'])));
			}

			$xml .= $system->xml_node('amount', array('value' => floor(($amount - 1) / self::$_limit) + 1));
			return $xml;
		}

		public static function set($feed, $id, $column, $value, System_1_0_0_User $user = null) #Update an entry's preference
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_digit($id) || !$system->is_digit($value) || preg_match('/\W/', $column)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			if(!$feed) #For updating the entries table
			{
				$query = $database->prepare("SELECT count(entry) FROM {$database->prefix}entry WHERE user = :user AND entry = :entry");
				$query->run(array(':user' => $user->id, ':entry' => $id));

				if(!$query->success) return false;

				if($query->column()) #If the entry exists, only update the category
					$query = $database->prepare("UPDATE {$database->prefix}entry SET $column = :$column WHERE user = :user AND entry = :entry");
				else #Otherwise, insert a new row with the category set
				{
					$db_system = $system->database('system', __METHOD__);
					if(!$db_system->success) return false;

					$query = $db_system->prepare("SELECT feed, date FROM {$db_system->prefix}entry WHERE id = :id");
					$query->run(array(':id' => $id));

					if(!$query->success) return false;
					$result = $query->row();

					#Insert the feed ID and the date as duplicate information for filter/sort purpose
					$query = $database->prepare("INSERT INTO {$database->prefix}entry (user, entry, $column) VALUES (:user, :entry, :$column)");
				}

				$query->run(array(':user' => $user->id, ':entry' => $id, ":$column" => $value));
			}
			else #For updating the feeds table
			{
				$query = $database->prepare("UPDATE {$database->prefix}subscribed SET $column = :$column WHERE user = :user AND feed = :feed");
				$query->run(array(":$column" => $value, ':user' => $user->id, ':feed' => $id));
			}

			return $query->success;
		}

		public static function show($id, System_1_0_0_User $user = null) #Get the entry information on the ID
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database['system'] = $system->database('system', __METHOD__);
			if(!$database['system']->success) return false;

			$query = $database['system']->prepare("SELECT feed, date FROM {$database['system']->prefix}entry WHERE id = :id");
			$query->run(array(':id' => $id)); #Get the entry information

			if(!$query->success) return false;
			$entry = $query->row();

			$database['user'] = $system->database('user', __METHOD__, $user);
			if(!$database['user']->success) return false;

			$query = $database['user']->prepare("SELECT feed, period FROM {$database['user']->prefix}subscribed WHERE user = :user AND feed = :feed");
			$query->run(array(':user' => $user->id, ':feed' => $entry['feed'])); #Get the feed's period mode

			if(!$query->success) return false;
			$feed = $query->row(); #Feed information

			$day = preg_replace('/ .+/', '', $entry['date']);
			$date = explode('-', $day); #The published date of the entry

			#Parameters to send back
			$info = array('id' => $feed['feed'], 'period' => (int) $feed['period'], 'year' => $date[0], 'month' => $date[1]);
			$value = array(':feed' => $feed['feed']); #Query parameters

			switch($info['period'])
			{
				case 0 : #If the period is set to daily, send the date
					$info['day'] = $date[2];

					$search = ' date LIKE :day';
					$value[':day'] = "$day %";
				break;

				case 1 : #If the period is set to weekly, send the week number
					$info['week'] = floor(($date[2] - 1) / 7) + 1;

					$search = ' date >= :start AND date <= :end';
					$max = date('t', mktime(0, 0, 0, $info['month'], 1, $info['year'])); #Get max day of the month

					$first = ($info['week'] - 1) * 7 + 1; #Get the starting day of the specified week
					if($first > $max) $first = $max; #If hitting the next month, set it as the last day

					$last = $info['week'] * 7; #Get the day of the week next
					if($last > $max) $last = $max; #If hitting the next month, set it as the last day

					$value[':start'] = self::_date($system, $info['year'], $info['month'], $first).' 00:00:00';
					$value[':end'] = self::_date($system, $info['year'], $info['month'], $last).' 23:59:59';
				break;

				case 2 : #Monthly span
					$search = ' date LIKE :day';
					$value[':day'] = self::_date($system, $info['year'], $info['month']).'-%';
				break;
			}

			$query = $database['system']->prepare("SELECT id FROM {$database['system']->prefix}entry WHERE feed = :feed AND $search ORDER BY date DESC");
			$query->run($value);

			if(!$query->success) return false;
			$index = 0; #Position of the entry in the list

			foreach($query->all() as $row)
			{
				$index++; #Count the position of the entry in the list
				if($row['id'] == $id) break;
			}

			$info['page'] = floor(($index - 1) / self::$_limit) + 1;
			return $system->xml_node('feed', $info);
		}
	}
?>

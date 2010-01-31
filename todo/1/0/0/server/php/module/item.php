<?php
	class Todo_1_0_0_Item
	{
		public static function add($title, $category = 0, System_1_0_0_User $user = null) #Add an entry
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_text($title)) return $log->param();
			if(!$system->is_digit($category)) $category = 0;

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);

			$query = $database->prepare("SELECT count(id) FROM {$database->prefix}todo WHERE user = :user AND title = :title");
			$query->run(array(':user' => $user->id, ':title' => $title));

			if(!$query->success) return false;
			if($query->column() != 0) return 2; #If same named entry exists, quit

			$query = $database->prepare("INSERT INTO {$database->prefix}todo (user, title, category, registered) VALUES (:user, :title, :category, :registered)");
			$query->run(array(':user' => $user->id, ':title' => $title, ':category' => $category, ':registered' => $system->date_datetime()));

			return $query->success;
		}

		public static function get($filter = 0, System_1_0_0_User $user = null) #Create a list of entries
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($filter)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$filter = $filter == 0 ? ' AND status = 0' : '';

			$query = $database->prepare("SELECT * FROM {$database->prefix}todo WHERE user = :user$filter ORDER BY year, month, day, hour, minute, status, category, title");
			$query->run(array(':user' => $user->id));

			return $query->success ? $query->all() : false;
		}

		public static function remove($id, System_1_0_0_User $user = null) #Remove an entry
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);

			$query = $database->prepare("DELETE FROM {$database->prefix}todo WHERE id = :id AND user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id));

			return $query->success;
		}

		public static function set($id, $title, $content, $category = 0, $status = 0, $year = null, $month = null, $day = null, $hour = null, $minute = null, System_1_0_0_User $user = null) #Set an entry
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id) || !$system->is_text($title)) return $log->param();

			if(!$system->is_digit($category)) $category = 0;
			if(!$system->is_digit($status)) $status = 0;

			if(!$system->is_text($content, true)) $content = '';

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$slots = array(); #Query update parameters
			foreach(explode(' ', 'title content category status year month day hour minute') as $part) $slots[] = "$part = :$part";

			$database = $system->database('user', __METHOD__, $user);

			$query = $database->prepare("UPDATE {$database->prefix}todo SET ".implode(', ', $slots)." WHERE id = :id AND user = :user");
			$query->run(array(':title' => $title, ':content' => $content, ':category' => $category, ':status' => $status, ':year' => $year, ':month' => $month, ':day' => $day, ':hour' => $hour, ':minute' => $minute, ':id' => $id, ':user' => $user->id));

			return $query->success;
		}
	}
?>

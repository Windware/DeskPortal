<?php
	class Calendar_1_0_0_Item
	{
		public static function get($year, $month, System_1_0_0_User $user = null) #Gets schedule listing
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!preg_match('/^[12]\d{3}$/', $year) || !preg_match('/^\d{1,2}$/', $month) || $month > 12 || $month < 1) return $log->param('');
			if($month < 10) $month = "0$month";

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT * FROM {$database->prefix}schedule WHERE user = :user AND day LIKE :day");
			$query->run(array(':user' => $user->id, ':day' => "$year-$month-%"));

			return $query->success ? $query->all() : false;
		}

		public static function remove($year, $month, $day, System_1_0_0_User $user = null) #Removes a schedule
		{
			$system = new System_1_0_0(__FILE__);

			$log = $system->log(__METHOD__);
			if(!$system->is_digit($year) || !$system->is_digit($month) || !$system->is_digit($day)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			#Pad the digits
			if($month < 10) $month = "0$month";
			if($day < 10) $day = "0$day";

			$query = $database->prepare("DELETE FROM {$database->prefix}schedule WHERE user = :user AND day = :day");
			$query->run(array(':user' => $user->id, ':day' => "$year-$month-$day"));

			return $query->success;
		}

		#Updates the schedule entry (Only allows a single schedule per day for this version)
		public static function set($day, $title, $content, $category, $start, $end, System_1_0_0_User $user = null)
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || !$system->is_text($title)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$values = array(':user' => $user->id, ':day' => $day);

			$query = $database->prepare("SELECT count(id) FROM {$database->prefix}schedule WHERE user = :user AND day = :day");
			$query->run($values);

			if(!$query->success) return false;

			$values[':title'] = $title;
			$values[':content'] = $content;

			$columns = $keys = $update = null; #Additional data to insert into the table

			if($system->is_digit($category))
			{
				$columns .= ', category';
				$keys .= ', :category';

				$update .= ', category = :category';
				$values[':category'] = $category;
			}

			$values[':start'] = preg_match('/^\d{1,2}:\d{1,2}$/', $start) ? "$day $start:00" : null;
			$values[':end'] = preg_match('/^\d{1,2}:\d{1,2}$/', $end) ? "$day $end:00" : null;

			if($query->column()) #If the record already exists, update the fields
			{
				$query = $database->prepare("UPDATE {$database->prefix}schedule SET title = :title, content = :content, start = :start, end = :end$update WHERE user = :user AND day = :day");
				$query->run($values);
			}
			else #Otherwise insert a new row
			{
				$query = $database->prepare("INSERT INTO {$database->prefix}schedule (user, day, title, content, start, end$columns) VALUES (:user, :day, :title, :content, :start, :end$keys)");
				$query->run($values);
			}

			return $query->success;
		}
	}
?>

<?php
	class Calendar_1_0_0_Group
	{
		public static function get(System_1_0_0_User $user = null) #Returns the user's categories
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT * FROM {$database->prefix}category WHERE user = :user");
			$query->run(array(':user' => $user->id));

			return $query->success ? $query->all() : false;
		}

		public static function remove($category, System_1_0_0_User $user = null) #Removes a category
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($category)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("DELETE FROM {$database->prefix}category WHERE user = :user AND id = :category");
			$query->run(array(':category' => $category, ':user' => $user->id));

			$query = $database->prepare("UPDATE {$database->prefix}schedule SET category = NULL WHERE user = :user AND category = :category");
			$query->run(array(':user' => $user->id, ':category' => $category));

			return $query->success;
		}

		public static function set($id, $name, $color, System_1_0_0_User $user = null) #Set a category
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id) || !$system->is_text($name) || !$system->is_text($color)) $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT count(id) FROM {$database->prefix}category WHERE user = :user AND id = :id");
			$query->run(array(':user' => $user->id, ':id' => $id));

			if(!$query->success) return false;

			if($query->column()) #If it exists, update the fields
			{
				$query = $database->prepare("UPDATE {$database->prefix}category SET name = :name, color = :color WHERE user = :user AND id = :id");
				$query->run(array(':name' => $name, ':color' => $color, ':user' => $user->id, ':id' => $id));
			}
			else #Otherwise, insert a new row
			{
				$query = $database->prepare("INSERT INTO {$database->prefix}category (user, name, color) VALUES (:user, :name, :color)");
				$query->run(array(':user' => $user->id, ':name' => $name, ':color' => $color));
			}

			return $query->success;
		}
	}
?>

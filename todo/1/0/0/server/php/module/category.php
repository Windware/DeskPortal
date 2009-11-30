<?php
	class Todo_1_0_0_Category
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

			if(!$query->success) return false;
			$xml = '';

			foreach($query->all() as $row) $xml .= $system->xml_node('category', $row, null, array('user'));
			return $xml;
		}

		public static function remove($id, System_1_0_0_User $user = null) #Remove a category
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("DELETE FROM {$database->prefix}category WHERE id = :id AND user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id));

			if(!$query->success) return false;

			#Reset the entries those were belonging to the group
			$query = $database->prepare("UPDATE {$database->prefix}todo SET category = 0 WHERE user = :user AND category = :category");
			$query->run(array(':user' => $user->id, ':category' => $id));

			return $query->success;
		}

		public static function set($name, $id = 0, System_1_0_0_User $user = null) #Set a category
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_text($name) || !$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT count(id), id FROM {$database->prefix}category WHERE user = :user AND name = :name");
			$query->run(array(':user' => $user->id, ':name' => $name));

			if(!$query->success) return false;
			$row = $query->row();

			if($row['count(id)'])
			{
				if($row['id'] == $id) return true; #Report success if nothing is changed
				return 10; #If a duplicate name is found on another row, report failure
			}

			if($id != 0) #If an ID is provided, update the fields
			{
				$query = $database->prepare("UPDATE {$database->prefix}category SET name = :name WHERE id = :id AND user = :user");
				$query->run(array(':name' => $name, ':user' => $user->id, ':id' => $id));
			}
			else #Otherwise, insert a new row
			{
				$query = $database->prepare("INSERT INTO {$database->prefix}category (user, name) VALUES (:user, :name)");
				$query->run(array(':user' => $user->id, ':name' => $name));
			}

			return $query->success;
		}
	}
?>

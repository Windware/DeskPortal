<?php
	class Addressbook_1_0_0_Item
	{
		public static function get($group, System_1_0_0_User $user = null) #Create a list of entries
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($group)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT * FROM {$database->prefix}address WHERE user = :user AND groups = :group ORDER BY name");
			$query->run(array(':user' => $user->id, ':group' => $group));

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
			if(!$database->success) return false;

			$query = $database->prepare("DELETE FROM {$database->prefix}address WHERE id = :id AND user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id)); #Remove the entry

			return $query->success;
		}

		public static function set($id, $param, System_1_0_0_User $user = null) #Edit an entry's information
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id) || !is_array($param)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$value = array(':user' => $user->id);

			$field = explode(' ', 'name sex age birth_year birth_month birth_day mail_main mail_mobile mail_alt phone mobile web address note groups updated');
			foreach($field as $section) $value[":$section"] = $param[$section];

			if($value[':groups'] == '') $value[':groups'] = 0; #Give '0' for uncategorized group
			$value[':updated'] = $system->date_datetime(); #Set the update time

			if($id == 0) #On new creation
			{
				$query = $database->prepare("SELECT count(id) FROM {$database->prefix}address WHERE user = :user AND name = :name");
				$query->run(array(':user' => $user->id, ':name' => $param['name']));

				if($query->column() != 0) return false; #If same named entry exists, quit

				$field[] = 'created'; #Add creation date field
				$value[':created'] = $value[':updated']; #Set the creation date

				foreach($field as $section)
				{
					$column .= ", $section";
					$index .= ", :$section";
				}

				$query = $database->prepare("INSERT INTO {$database->prefix}address (user$column) VALUES (:user$index)");
				$query->run($value);
			}
			else
			{
				$column = array();
				foreach($field as $section) $column[] = "$section = :$section";

				$column = implode(', ', $column);
				$value[':id'] = $id;

				$query = $database->prepare("UPDATE {$database->prefix}address SET $column WHERE id = :id AND user = :user");
				$query->run($value); #Update its name
			}

			return $query->success;
		}
	}
?>

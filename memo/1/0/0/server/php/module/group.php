<?php
	class Memo_1_0_0_Group
	{
		public static function get(System_1_0_0_User $user = null) #Get groups
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT id, name FROM {$database->prefix}groups WHERE user = :user ORDER BY name");
			$query->run(array(':user' => $user->id));

			if(!$query->success) return false;
			$xml = '';

			foreach($query->all() as $row) $xml .= $system->xml_node('group', $row);
			return $xml;
		}

		public static function remove($group, System_1_0_0_User $user = null) #Removes a group
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($group)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$database->begin(); #Make sure these are atomic

			$query = $database->prepare("DELETE FROM {$database->prefix}relation WHERE user = :user AND groups = :group");
			$query->run(array(':user' => $user->id, ':group' => $group));

			if(!$query->success) $database->rollback();
			else
			{
				$query = $database->prepare("DELETE FROM {$database->prefix}groups WHERE id = :group AND user = :user");
				$query->run(array(':group' => $group, ':user' => $user->id));

				$query->success ? $database->commit() : $database->rollback();
			}

			return $query->success;
		}

		public static function set($name, $id = 0, System_1_0_0_User $user = null) #Set a group
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_text($name) || !$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT count(id), id FROM {$database->prefix}groups WHERE user = :user AND name = :name");
			$query->run(array(':user' => $user->id, ':name' => $name));

			if(!$query->success) return false;
			$row = $query->row();

			if($row['count(id)'])
			{
				if($row['id'] == $id) return true; #Report success if nothing is changed
				return false; #If a duplicate name is found on another row, report failure
			}

			if($id != 0) #If an ID is provided, update the fields
			{
				$query = $database->prepare("UPDATE {$database->prefix}groups SET name = :name WHERE id = :id AND user = :user");
				$query->run(array(':name' => $name, ':id' => $id, ':user' => $user->id));
			}
			else #Otherwise, insert a new row
			{
				$query = $database->prepare("INSERT INTO {$database->prefix}groups (user, name) VALUES (:user, :name)");
				$query->run(array(':user' => $user->id, ':name' => $name));
			}

			return $query->success;
		}
	}
?>
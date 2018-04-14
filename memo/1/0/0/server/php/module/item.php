<?php

class Memo_1_0_0_Item
{
	public static function get(System_1_0_0_User $user = null) #Create a list of memos
	{
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		if($user === null) $user = $system->user();
		if(!$user->valid) return false;

		$database = $system->database('user', __METHOD__, $user);
		if(!$database->success) return false;

		$query = $database->prepare("SELECT * FROM {$database->prefix}memo WHERE user = :user ORDER BY name");
		if(!$query->run([':user' => $user->id])) return false;

		$result = $query->all();
		$query = $database->prepare("SELECT groups FROM {$database->prefix}relation WHERE user = :user AND memo = :memo");

		$revision = $database->prepare("SELECT time FROM {$database->prefix}revision WHERE user = :user AND memo = :memo ORDER BY time DESC LIMIT 1");
		$list = [];

		foreach($result as $row) #For all of the memo
		{
			if(!$query->run([':memo' => $row['id'], ':user' => $user->id])) return false; #Find its related groups

			$groups = [];
			foreach($query->all() as $relation) $groups[] = $relation['groups'];

			if(!$revision->run([':user' => $user->id, ':memo' => $row['id']])) return false; #Get the memo's revision information

			#Create the list of memo information with the belonging groups concatenated
			$attributes = ['id' => $row['id'], 'name' => $row['name'], 'groups' => implode($groups, ','), 'last' => $revision->column()];
			$list[] = $attributes;
		}

		return $list;
	}

	public static function remove($id, System_1_0_0_User $user = null) #Remove a memo
	{
		$system = new System_1_0_0(__FILE__);

		$log = $system->log(__METHOD__);
		if(!$system->is_digit($id)) return $log->param();

		if($user === null) $user = $system->user();
		if(!$user->valid) return false;

		$database = $system->database('user', __METHOD__, $user);
		if(!$database->success) return false;

		$query = $database->prepare("DELETE FROM {$database->prefix}relation WHERE user = :user AND memo = :memo");
		if(!$query->run([':user' => $user->id, ':memo' => $id])) return false; #Remove the group relation

		$query = $database->prepare("DELETE FROM {$database->prefix}revision WHERE user = :user AND memo = :memo");
		if(!$query->run([':user' => $user->id, ':memo' => $id])) return false; #Remove the memo revision

		$query = $database->prepare("DELETE FROM {$database->prefix}memo WHERE id = :id AND user = :user");
		return $query->run([':id' => $id, ':user' => $user->id]); #Remove the memo entry
	}

	public static function save($id, $content, System_1_0_0_User $user = null) #Save memo content
	{
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		if(!$system->is_digit($id) || !$system->is_text($content, true)) return $log->param();

		if($user === null) $user = $system->user();
		if(!$user->valid) return false;

		$database = $system->database('user', __METHOD__, $user);
		if(!$database->success) return false;

		$query = $database->prepare("DELETE FROM {$database->prefix}revision WHERE user = :user AND memo = :memo");
		if(!$query->run([':user' => $user->id, ':memo' => $id])) return false; #No revision support for this version

		$query = $database->prepare("INSERT INTO {$database->prefix}revision (user, memo, time, content) VALUES (:user, :memo, :time, :content)");
		return $query->run([':user' => $user->id, ':memo' => $id, ':time' => gmdate($system->date_datetime()), ':content' => $content]);
	}

	public static function set($name, $groups, $id = 0, System_1_0_0_User $user = null) #Edit a memo's information
	{
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		if(!$system->is_text($name) || !$system->is_digit($id)) return $log->param();
		if(!is_array($groups)) $groups = [];

		if($user === null) $user = $system->user();
		if(!$user->valid) return false;

		$database = $system->database('user', __METHOD__, $user);
		if(!$database->success) return false;

		if($id == 0) #On new creation
		{
			$query = $database->prepare("SELECT count(id) FROM {$database->prefix}memo WHERE user = :user AND name = :name");
			$query->run([':user' => $user->id, ':name' => $name]);

			if($query->column()) return 2; #If same named entry exists, quit

			$query = $database->prepare("INSERT INTO {$database->prefix}memo (user, name) VALUES (:user, :name)");
			if(!$query->run([':user' => $user->id, ':name' => $name])) return false;

			$id = $database->id();
		}
		else
		{
			$query = $database->prepare("SELECT * FROM {$database->prefix}memo WHERE id = :id AND user = :user");
			if(!$query->run([':id' => $id, ':user' => $user->id])) return false;

			$result = $query->row(); #Get the memo info

			if($name != $result['name']) #If the name has changed
			{
				$query = $database->prepare("SELECT count(id) FROM {$database->prefix}memo WHERE user = :user AND name = :name");
				$query->run([':user' => $user->id, ':name' => $name]);

				if($query->column()) return 2; #If same named entry exists, quit

				$query = $database->prepare("UPDATE {$database->prefix}memo SET name = :name WHERE id = :id AND user = :user");
				if(!$query->run([':name' => $name, ':id' => $id, ':user' => $user->id])) return false; #Update its name
			}
		}

		$query = $database->prepare("DELETE FROM {$database->prefix}relation WHERE user = :user AND memo = :memo");
		if(!$query->run([':user' => $user->id, ':memo' => $id])) return false; #Clear out the current relation

		if(count($groups))
		{
			if(!$database->begin()) return false; #Start a transaction to make multiple group relation atomic
			$query = $database->prepare("INSERT INTO {$database->prefix}relation (user, memo, groups) VALUES (:user, :memo, :groups)");

			foreach($groups as $relation) #For all of the provided groups
			{
				if(!$system->is_digit($relation)) continue;
				if(!$query->run([':user' => $user->id, ':memo' => $id, ':groups' => $relation])) return $database->rollback() && false; #Relate it to the memo
			}

			if(!$database->commit()) return $database->rollback() && false; #Commit the sequence
		}

		return true;
	}

	public static function show($id, System_1_0_0_User $user = null) #Load a memo content
	{
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		if(!$system->is_digit($_GET['id'])) return $log->param();

		if($user === null) $user = $system->user();
		if(!$user->valid) return false;

		$database = $system->database('user', __METHOD__, $user);
		if(!$database->success) return false;

		$query = $database->prepare("SELECT content FROM {$database->prefix}revision WHERE user = :user AND memo = :memo ORDER BY time DESC LIMIT 1");
		return $query->run([':user' => $user->id, ':memo' => $_GET['id']]) ? $query->column() : false;
	}
}

?>

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
			$query->run(array(':user' => $user->id));

			if(!$query->success) return false;
			$result = $query->all();

			$query = $database->prepare("SELECT groups FROM {$database->prefix}relation WHERE user = :user AND memo = :memo");

			#Get the memo's revision information
			$revision = $database->prepare("SELECT time FROM {$database->prefix}revision WHERE user = :user AND memo = :memo ORDER BY time DESC LIMIT 1");

			$xml = '';

			foreach($result as $row) #For all of the memo
			{
				$query->run(array(':memo' => $row['id'], ':user' => $user->id)); #Find its related groups
				if(!$query->success) return false;

				$groups = array();
				foreach($query->all() as $relation) $groups[] = $relation['groups'];

				$revision->run(array(':user' => $user->id, ':memo' => $row['id']));
				if(!$revision->success) return false;

				#Create the list of memo information with the belonging groups concatenated
				$attributes = array('id' => $row['id'], 'name' => $row['name'], 'groups' => implode($groups, ','), 'last' => $revision->column());
				$xml .= $system->xml_node('memo', $attributes);
			}

			return $xml;
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
			$query->run(array(':user' => $user->id, ':memo' => $_GET['id']));

			return $query->success ? $system->xml_node('content', null, $system->xml_data($query->column())) : false;
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
			$query->run(array(':user' => $user->id, ':memo' => $id)); #Remove the group relation

			if(!$query->success) return false;

			$query = $database->prepare("DELETE FROM {$database->prefix}revision WHERE user = :user AND memo = :memo");
			$query->run(array(':user' => $user->id, ':memo' => $id)); #Remove the memo revision

			if(!$query->success) return false;

			$query = $database->prepare("DELETE FROM {$database->prefix}memo WHERE id = :id AND user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id)); #Remove the memo entry

			return $query->success;
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
			$query->run(array(':user' => $user->id, ':memo' => $id)); #No revision support for this version

			if(!$query->success) return false;

			$query = $database->prepare("INSERT INTO {$database->prefix}revision (user, memo, time, content) VALUES (:user, :memo, :time, :content)");
			$query->run(array(':user' => $user->id, ':memo' => $id, ':time' => date($system->date_datetime()), ':content' => $content));

			return $query->success;
		}

		public static function set($name, $groups, $id = 0, System_1_0_0_User $user = null) #Edit a memo's information
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_text($name)) return $log->param();

			if(!is_array($groups)) $groups = array();
			if(!$system->is_digit($id)) $id = 0;

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			if($id == 0) #On new creation
			{
				$query = $database->prepare("SELECT count(id) FROM {$database->prefix}memo WHERE user = :user AND name = :name");
				$query->run(array(':user' => $user->id, ':name' => $name));

				if($query->column() == 0) #If same named entry does not exist
				{
					$query = $database->prepare("INSERT INTO {$database->prefix}memo (user, name) VALUES (:user, :name)");
					$query->run(array(':user' => $user->id, ':name' => $name));

					if(!$query->success) return false;
					$id = $database->id();
				}
				else return false;
			}
			else
			{
				$query = $database->prepare("SELECT * FROM {$database->prefix}memo WHERE id = :id AND user = :user");
				$query->run(array(':id' => $id, ':user' => $user->id));

				if(!$query->success) return false;
				$result = $query->row(); #Get the memo info

				if($name != $result['name']) #If the name has changed
				{
					$query = $database->prepare("UPDATE {$database->prefix}memo SET name = :name WHERE id = :id AND user = :user");
					$query->run(array(':name' => $name, ':id' => $id, ':user' => $user->id)); #Update its name

					if(!$query->success) return false;
				}
			}

			$query = $database->prepare("DELETE FROM {$database->prefix}relation WHERE user = :user AND memo = :memo");
			$query->run(array(':user' => $user->id, ':memo' => $id)); #Clear out the current relation

			if(!$query->success) return false;

			if(count($groups))
			{
				$database->begin(); #Start a transaction to make multiple group relation atomic
				$query = $database->prepare("INSERT INTO {$database->prefix}relation (user, memo, groups) VALUES (:user, :memo, :groups)");

				foreach($groups as $relation) #For all of the provided groups
				{
					if(!$system->is_digit($relation)) continue;

					$query->run(array(':user' => $user->id, ':memo' => $id, ':groups' => $relation)); #Relate it to the memo
					if($query->success) continue;

					$database->rollback(); #On failure, rollback
					return false;
				}

				$database->commit(); #Commit the sequence
			}

			return true;
		}
	}
?>

<?php
	class Toolbar_1_0_0
	{
		public static function get(System_1_0_0_User $user = null) #Get list of opened bars
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);

			$query = $database->prepare("SELECT id FROM {$database->prefix}display WHERE user = :user");
			$query->run(array(':user' => $user->id));

			if(!$query->success) return false;

			$list = array();
			foreach($query->all() as $row) $list[] = $row['id'];

			return $list;
		}

		public static function selection($index, $feature = null, $method = null, $source = null, $target = null, System_1_0_0_User $user = null) #Gets or sets the toolbar selection
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($index)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);

			if(strlen($feature) && strlen($method) && strlen($source)) #Save the choices
			{
				$query = $database->prepare("REPLACE INTO {$database->prefix}selection (user, bar, feature, method, source, target) VALUES (:user, :bar, :feature, :method, :source, :target)");
				$query->run(array(':user' => $user->id, ':bar' => $index, ':feature' => $feature, ':method' => $method, ':source' => $source, ':target' => $target));

				return $query->success;
			}

			$query = $database->prepare("SELECT feature, method, source, target FROM {$database->prefix}selection WHERE user = :user AND bar = :bar");
			$query->run(array(':user' => $user->id, ':bar' => $index));

			return $query->success ? $query->row() : false;
		}

		public static function set($index, $mode, System_1_0_0_User $user = null) #Keep the presence of a bar
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($index)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);

			if($mode)
			{
				$query = $database->prepare("REPLACE INTO {$database->prefix}display (user, id) VALUES (:user, :id)");
				$query->run(array(':user' => $user->id, ':id' => $index));

				return $query->success;
			}

			if(!$database->begin()) return false;

			$query = $database->prepare("DELETE FROM {$database->prefix}selection WHERE user = :user AND bar = :bar");
			$query->run(array(':user' => $user->id, ':bar' => $index));

			if(!$query->success) return $database->rollback() && false;

			$query = $database->prepare("DELETE FROM {$database->prefix}display WHERE user = :user AND id = :id");
			$query->run(array(':user' => $user->id, ':id' => $index));

			return $query->success && $database->commit() || $database->rollback() && false;
		}
	}
?>

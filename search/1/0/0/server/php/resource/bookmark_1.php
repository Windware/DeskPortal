<?php
	class Search_1_0_0_Resource_Bookmark_1
	{
		public $count = 0;

		public $result = array();

		public function __construct($phrase, $limit, $page, System_1_0_0_User $user = null)
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$name = explode('_', __CLASS__);

			$database = $system->database('user', __METHOD__, $user, strtolower($name[5]), $name[6]);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT id, name FROM {$database->prefix}bookmark WHERE user = :user");
			$query->run(array(':user' => $user->id)); #Get all bookmarks

			if(!$query->success) return false;

			$result = $query->all();
			if(!count($result)) return true;

			$list = $param = $target = array();

			foreach($result as $index => $row)
			{
				$miss = false;
				foreach($phrase as $term) if(!stristr($row['name'], $term)) $miss = true;

				if(!$miss) $list[] = $row['id']; #If found in the name, mark it
				else #Otherwise, list up to be searched inside the system database for matches in other fields
				{
					$value = $target[] = ":i{$index}d";
					$param[$value] = $row['id'];
				}

				$title[$row['id']] = $row['name']; #Keep the bookmark names
			}

			if(count($target)) #NOTE : Not searching for categories as the interface provides it and can clutter the result
			{
				foreach($phrase as $index => $term)
				{
					$search .= " AND (address LIKE :s{$index}id $database->escape OR title LIKE :s{$index}id $database->escape)";
					$param[":s{$index}id"] = '%'.$system->database_escape($term).'%';
				}

				$target = implode(', ', $target);

				$database = $system->database('system', __METHOD__, null, strtolower($name[5]), $name[6]);
				if(!$database->success) return false;

				$query = $database->prepare("SELECT id FROM {$database->prefix}address WHERE id IN ($target)$search");
				$query->run($param); #Look for bookmark address and title

				if(!$query->success) return false;
				foreach($query->all() as $row) $list[] = $row['id'];
			}

			if(!count($list)) return true; #If none found, quit

			$database = $system->database('user', __METHOD__, $user, strtolower($name[5]), $name[6]);
			if(!$database->success) return false;

			$target = array();
			$param = array(':user'=> $user->id);

			foreach($list as $index => $id)
			{
				$value = $target[] = ":i{$index}d";
				$param[$value] = $id;
			}

			$target = implode(', ', $target);

			$query = $database->prepare("SELECT bookmark, category FROM {$database->prefix}relation WHERE user = :user AND bookmark IN ($target)");
			$query->run($param); #Get the belonging group for that bookmark

			if(!$query->success) return false;

			$relation = array();
			foreach($query->all() as $row) $relation[$row['bookmark']][] = $row['category'];

			$this->count = count($list);

			foreach(array_slice($list, ($page - 1) * $limit, $limit) as $id) #Against all the valid bookmarks, add its ID, name and the list of groups
			{
				$group = is_array($relation[$id]) ? implode(',', $relation[$id]) : '';
				$this->result['item'][] = array('id' => $id, 'text' => $title[$id], 'group' => $group);
			}
		}
	}
?>

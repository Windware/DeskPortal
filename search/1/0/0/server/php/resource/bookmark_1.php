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

			#Get all bookmarks
			$query = $database->prepare("SELECT id, name FROM {$database->prefix}bookmark WHERE user = :user");
			$query->run(array(':user' => $user->id));

			if(!$query->success) return false;
			$result = $query->all();

			if(!count($result)) return true;
			$list = $param = $value = array();

			foreach($result as $index => $row)
			{
				if(stristr($phrase, $row['name'])) $list[] = $row['id']; #If found in the name, mark it
				else #Otherwise, list up to be searched inside the system database for matches in other fields
				{
					$param[] = ":id{$index}_index";
					$value[":id{$index}_index"] = $row['id'];
				}

				$bookmark[$row['id']] = $row['name']; #Keep the bookmark names
			}

			#NOTE : Not searching for categories as the interface provides it and could clutter the result

			$database = $system->database('system', __METHOD__, null, strtolower($name[5]), $name[6]);
			if(!$database->success) return false;

			if(!count($param)) return true;

			$query = $database->prepare("SELECT id, address, title FROM {$database->prefix}address WHERE id IN (".implode(',', $param).')');
			$query->run($value); #Look for bookmark address and title

			foreach($query->all() as $row)
			{
				if(stristr($row['address'], $phrase) || stristr($row['title'], $phrase)) $list[] = $row['id'];
				if(!$bookmark[$row['id']]) $bookmark[$row['id']] = $row['address']; #Complement the title by address if none set
			}

			if(!count($list)) return true; #If none found, quit

			$limiter = array();
			$value = array(':user'=> $user->id);

			foreach($list as $index => $id)
			{
				$limiter[] = ":id{$index}_index";
				$value[":id{$index}_index"] = $id;
			}

			$database = $system->database('user', __METHOD__, $user, strtolower($name[5]), $name[6]);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT bookmark, category FROM {$database->prefix}relation WHERE user = :user AND bookmark IN (".implode(',', $limiter).')');
			$query->run($value); #Check out the belonging group for that bookmark

			$relation = array();
			foreach($query->all() as $row) $relation[$row['bookmark']][] = $row['category'];

			$this->count = count($list);

			foreach(array_slice($list, ($page - 1) * $limit, $limit) as $id) #Against all the valid bookmarks, add its ID, name and the list of groups
			{
				$group = is_array($relation[$id]) ? implode(',', $relation[$id]) : '';
				$this->result['item'][] = array('id' => $id, 'text' => $bookmark[$id], 'group' => $group);
			}
		}
	}
?>

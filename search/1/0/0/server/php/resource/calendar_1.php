<?php
	class Search_1_0_0_Resource_Calendar_1
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
			$sql = array(); #List of queries to make

			#Look for matches in category names
			$sql[] = "SELECT sc.id as id FROM {$database->prefix}category as cat, {$database->prefix}schedule as sc WHERE cat.user = :user AND cat.name LIKE :phrase $database->escape AND sc.user = :user AND cat.id = sc.category";

			#Look for matches in schedule title and content
			$sql[] = "SELECT id FROM {$database->prefix}schedule WHERE user = :user AND title LIKE :phrase $database->escape OR content LIKE :phrase $database->escape";

			$list = array(); #List of item ID that matched

			foreach($sql as $run) #Retrieve the item ID by looking for matches
			{
				$query = $database->prepare($run);
				$query->run(array(':user' => $user->id, ':phrase' => '%'.$system->database_escape($phrase).'%'));

				if(!$query->success) return false;
				foreach($query->all() as $row) $list[$row['id']] = true; #Keep the item ID found
			}

			$this->count = count($list);
			if(!$this->count) return false;

			$param = array(); #Query parameters
			$value = array(':user' => $user->id);

			foreach(array_keys($list) as $index => $id) #FIXME - Query length could become huge
			{
				$param[] = "id = :id{$index}_index";
				$value[":id{$index}_index"] = $id;
			}

			$paging = Search_1_0_0_Item::limit($limit, $page); #Result limit

			$query = $database->prepare("SELECT day, title FROM {$database->prefix}schedule WHERE ".implode(' OR ', $param)." AND user = :user ORDER BY day DESC $paging");
			$query->run($value);

			if(!$query->success) return false;
			foreach($query->all() as $row) $this->result['item'][] = array('id' => $row['day'], 'text' => $row['title']);
		}
	}
?>

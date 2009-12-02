<?php
	class Search_1_0_0_Resource_Memo_1
	{
		public $count = 0;

		public $result = array();

		public function __construct($phrase, $limit, $page, System_1_0_0_User $user = null)
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_text($phrase)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$name = explode('_', __CLASS__);
			$database = $system->database('user', __METHOD__, $user, strtolower($name[5]), $name[6]);

			if(!$database->success) return false;

			$sql = array(); #List of queries to make

			#Look for matches in group names
			$sql[] = "SELECT rel.memo as id FROM {$database->prefix}groups as grp, {$database->prefix}relation as rel WHERE grp.user = :user AND grp.name LIKE :phrase $database->escape AND rel.user = :user AND grp.id = rel.groups";

			#Look for matches in memo names
			$sql[] = "SELECT id FROM {$database->prefix}memo WHERE user = :user AND name LIKE :phrase $database->escape";

			#Look for matches in memo contents
			$sql[] = "SELECT memo as id FROM {$database->prefix}revision WHERE user = :user AND content LIKE :phrase $database->escape";

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

			foreach(array_keys($list) as $index => $id)
			{
				$param[] = "id = :id{$index}_index";
				$value[":id{$index}_index"] = $id;
			}

			$paging = Search_1_0_0_Item::limit($limit, $page); #Result limit

			$query = $database->prepare("SELECT id, name FROM {$database->prefix}memo WHERE ".implode(' OR ', $param)." AND user = :user $paging");
			$query->run($value);

			if(!$query->success) return false;
			$query = $database->prepare("SELECT groups FROM {$database->prefix}relation WHERE user = :user AND memo = :memo ORDER BY groups LIMIT 1");

			foreach($query->all() as $row)
			{
				#Pick a single group as a reference
				$query->run(array(':user' => $user->id, ':memo' => $row['id']));

				if(!$query->success) return false;

				$group = $query->column();
				if(!$group) $group = 0;

				$this->result['item'][] = array('id' => $row['id'], 'group' => $group, 'text' => $row['name']);
			}
		}
	}
?>

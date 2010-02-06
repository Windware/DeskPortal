<?php
	class Search_1_0_0_Resource_Memo_1
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
			$param = array(':user' => $user->id);

			foreach($phrase as $index => $term)
			{
				$search .= " AND (memo.name LIKE :s{$index}id $database->escape OR rev.content LIKE :s{$index}id $database->escape)";
				$param[":s{$index}id"] = '%'.$system->database_escape($term).'%';
			}

			$query = $database->prepare("SELECT memo.id, memo.name FROM {$database->prefix}memo as memo LEFT JOIN {$database->prefix}revision as rev ON memo.id = rev.memo
			WHERE memo.user = :user$search GROUP BY memo.id ORDER BY rev.time DESC");

			$query->run($param);
			if(!$query->success) return false;

			$this->count = count($all = $query->all());
			if(!$this->count) return true;

			$all = array_slice($all, ($page - 1) * $limit, $limit); #Slice out the required list

			$target = array();
			$param = array(':user' => $user->id);

			foreach($all as $index => $row)
			{
				$value = $target[] = ":i{$index}d";
				$param[$value] = $row['id'];
			}

			if(count($target))
			{
				$target = implode(', ', $target);

				$query = $database->prepare("SELECT memo, groups FROM {$database->prefix}relation WHERE memo IN ($target) AND user = :user GROUP BY memo ORDER BY groups");
				$query->run($param);

				if(!$query->success) return false;
				foreach($query->all() as $row) $relation[$row['memo']] = $row['groups'];
			}

			foreach($all as $row) $this->result['item'][] = array('id' => $row['id'], 'group' => $relation[$row['id']] ? $relation[$row['id']] : 0, 'text' => $row['name']);
		}
	}
?>

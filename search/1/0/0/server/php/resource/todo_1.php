<?php
	class Search_1_0_0_Resource_Todo_1
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
				$search .= " AND (todo.title LIKE :s{$index}id $database->escape OR cat.name LIKE :s{$index}id $database->escape OR todo.content LIKE :s{$index}id $database->escape)";
				$param[":s{$index}id"] = '%'.$system->database_escape($term).'%';
			}

			$base = "FROM {$database->prefix}todo as todo LEFT JOIN {$database->prefix}category as cat ON todo.category = cat.id
			WHERE todo.user = :user$search ORDER BY registered DESC";

			$query = $database->prepare("SELECT todo.id, todo.title $base"); #Look for matches in category names, entry title or entry content
			$query->run($param);

			if(!$query->success) return false;

			$this->count = count($all = $query->all());
			foreach(array_slice($all, ($page - 1) * $limit, $limit) as $row) $this->result['item'][] = array('id' => $row['id'], 'text' => $row['title']);
		}
	}
?>

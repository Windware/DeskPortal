<?php

class Search_1_0_0_Resource_Calendar_1
{
	public $count = 0;

	public $result = [];

	public function __construct($phrase, $limit, $page, System_1_0_0_User $user = null)
	{
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		if($user === null) $user = $system->user();
		if(!$user->valid) return false;

		$name = explode('_', __CLASS__);
		$database = $system->database('user', __METHOD__, $user, strtolower($name[5]), $name[6]);

		if(!$database->success) return false;
		$param = [':user' => $user->id];

		foreach($phrase as $index => $term)
		{
			$search .= " AND (sc.title LIKE :s{$index}id $database->escape OR cat.name LIKE :s{$index}id $database->escape OR sc.content LIKE :s{$index}id $database->escape)";
			$param[":s{$index}id"] = '%' . $system->database_escape($term) . '%';
		}

		$query = $database->prepare("SELECT sc.day, sc.title FROM {$database->prefix}schedule as sc LEFT JOIN {$database->prefix}category as cat ON sc.category = cat.id WHERE sc.user = :user$search ORDER BY day DESC");
		$query->run($param);

		if(!$query->success) return false;

		$this->count = count($all = $query->all());
		foreach(array_slice($all, ($page - 1) * $limit, $limit) as $row) $this->result['item'][] = ['id' => $row['day'], 'text' => $row['title']];
	}
}

?>

<?php

class Search_1_0_0_Resource_Addressbook_1
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

		#NOTE : Not checking on group names as grouping exists on the app itself and can clutter the result
		$target = explode(' ', 'name mail_main mail_mobile mail_alt phone mobile web address note'); #List of columns to check
		$param = [':user' => $user->id];

		foreach($phrase as $index => $term)
		{
			$column = [];
			foreach($target as $item) $column[] = "$item LIKE :s{$index}id $database->escape"; #Construct column query string

			$search .= ' AND (' . implode(' OR ', $column) . ')';
			$param[":s{$index}id"] = '%' . $system->database_escape($term) . '%';
		}

		$query = $database->prepare("SELECT id, name, groups FROM {$database->prefix}address WHERE user = :user$search ORDER BY name");
		$query->run($param);

		if(!$query->success) return false;

		$this->count = count($all = $query->all());
		foreach(array_slice($all, ($page - 1) * $limit, $limit) as $row) $this->result['item'][] = ['id' => $row['id'], 'text' => $row['name'], 'groups' => $row['groups']];
	}
}

?>

<?php
	class Search_1_0_0_Resource_Headline_1
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

			$query = $database->prepare("SELECT feed FROM {$database->prefix}subscribed WHERE user = :user");
			$query->run(array(':user' => $user->id)); #Search for all subscribed feeds

			if(!$query->success) return false;

			$sub = array(); #List of subscription
			foreach($query->all() as $row) $sub[] = $row['feed'];

			if(!count($sub)) return true;
			#NOTE : Not searching for categories as the interface provides it and can clutter the result

			$database = $system->database('system', __METHOD__, null, strtolower($name[5]), $name[6]);
			if(!$database->success) return false;

			$param = $target = array();

			foreach($sub as $index => $id)
			{
				$value = $target[] = ":i{$index}d";
				$param[$value] = $id;
			}

			$target = implode(',', $target);

			foreach($phrase as $index => $term)
			{
				$search .= " AND (subject LIKE :s{$index}id $database->escape OR section LIKE :s{$index}id $database->escape)";
				$param[":s{$index}id"] = '%'.$system->database_escape($term).'%';
			}

			$query = $database->prepare("SELECT id, date, subject FROM {$database->prefix}entry WHERE feed IN ($target)$search ORDER BY date DESC");
			$query->run($param);

			if(!$query->success) return false;

			$this->count = count($all = $query->all());
			foreach(array_slice($all, ($page - 1) * $limit, $limit) as $row) $this->result['item'][] = array('id' => $row['id'], 'date' => preg_replace('/ .+/', '', $row['date']), 'text' => $row['subject']);
		}
	}
?>

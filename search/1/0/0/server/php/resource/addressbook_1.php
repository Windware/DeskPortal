<?php
	class Search_1_0_0_Resource_Addressbook_1
	{
		public $count = 0;

		public $result = array();

		public function __construct($phrase, $limit, $page, System_1_0_0_User $user = null)
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_text($phrase) || !$system->is_digit($limit)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$name = explode('_', __CLASS__);
			$database = $system->database('user', __METHOD__, $user, strtolower($name[5]), $name[6]);

			if(!$database->success) return false;

			#NOTE : Not checking on group names as grouping exists on the app itself and can clutter the result
			$target = explode(' ', 'name mail_main mail_mobile mail_alt phone mobile web address note'); #List of columns to check
			$column = array(); #List of patterns to check

			foreach($target as $item) $column[] = "$item LIKE :phrase $database->escape"; #Construct column query string
			$value = array(':user' => $user->id, ':phrase' => '%'.$system->database_escape($phrase).'%'); #Query values

			$query = $database->prepare("SELECT id, name, groups FROM {$database->prefix}address WHERE user = :user AND (".implode(' OR ', $column).')');
			$query->run($value);

			if(!$query->success) return false;

			$list = array();
			foreach($query->all() as $row) $list[] = array('id' => $row['id'], 'name' => $row['name'], 'groups' => $row['groups']);

			$this->count = count($list);

			foreach(array_slice($list, ($page - 1) * $limit, $limit) as $param) #Dump the result into the object property
				$this->result['item'][] = array('id' => $param['id'], 'text' => $param['name'], 'groups' => $param['groups']);
		}
	}
?>

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
			$target = explode(' ', 'name mail_user mail_domain phone mobile web address note'); #List of columns to check
			$column = array(); #List of patterns to check

			foreach($target as $item) $column[] = "$item LIKE :phrase"; #Construct column query string
			$column = implode(' OR ', $column);

			$value = array(':user' => $user->id, ':phrase' => "%$phrase%"); #Query values

			$query = $database->prepare("SELECT id, name, groups FROM {$database->prefix}address WHERE user = :user AND ($column)");
			$query->run($value);

			if(!$query->success) return false;

			$list = array();
			foreach($query->all() as $row) $list[$row['id']] = array('name' => $row['name'], 'groups' => $row['groups']);

			if(count($mail = explode('@', $phrase, 2)) >= 2)  #If the query looks like a mail address
			{
				#Match against the user and the domain field
				$value[':mail_user'] = "%{$mail[0]}";
				$value[':mail_domain'] = "{$mail[1]}%";

				unset($value[':phrase']);

				$query = $database->prepare("SELECT id, name, groups FROM {$database->prefix}address WHERE user = :user AND mail_user LIKE :mail_user AND mail_domain LIKE :mail_domain");
				$query->run($value);

				if(!$query->success) return false;
				foreach($query->all() as $row) $list[$row['id']] = array('name' => $row['name'], 'groups' => $row['groups']);
			}

			$this->count = count($list);
			$start = ($page - 1) * $limit + 1;

			foreach($list as $id => $param) #Dump the result into the object property
			{
				if(++$index < $start) continue;
				if($index == $start + $limit) break;

				$this->result['item'][] = array('id' => $id, 'text' => $param['name'], 'groups' => $param['groups']);
			}
		}
	}
?>

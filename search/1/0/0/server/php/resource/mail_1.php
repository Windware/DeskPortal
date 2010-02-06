<?php
	class Search_1_0_0_Resource_Mail_1
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
				foreach(explode(' ', 'from to cc bcc') as $section) $filter .= " OR _$section.name LIKE :s{$index}id $database->escape OR _$section.address LIKE :s{$index}id $database->escape";

				$search .= " AND (mail.subject LIKE :s{$index}id $database->escape$filter OR attachment.name LIKE :s{$index}id $database->escape OR mail.plain LIKE :s{$index}id $database->escape)";
				$param[":s{$index}id"] = '%'.$system->database_escape($term).'%';
			}

			foreach(explode(' ', 'from to cc bcc') as $section) $target .= " LEFT JOIN {$database->prefix}$section as _$section ON mail.id = _$section.mail";

			$query = $database->prepare("SELECT mail.id, mail.subject, mail.sent, _from.name, _from.address FROM {$database->prefix}mail as mail$target
			LEFT JOIN {$database->prefix}attachment as attachment ON mail.id = attachment.mail WHERE mail.user = :user$search ORDER BY mail.sent DESC");

			$query->run($param);
			if(!$query->success) return false;

			$this->count = count($all = $query->all());
			foreach(array_slice($all, ($page - 1) * $limit, $limit) as $row) $this->result['item'][] = array('id' => $row['id'], 'text' => $row['subject'], 'date' => $row['sent'], 'from' => $row['address'], 'name' => $row['name']);
		}
	}
?>

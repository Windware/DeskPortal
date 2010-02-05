<?php
	class Search_1_0_0_Resource_Mail_1
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

			foreach(explode(' ', 'from to cc bcc') as $section)
			{
				$target .= " LEFT JOIN {$database->prefix}$section as m_$section ON mail.id = m_$section.mail";
				$filter .= " OR m_$section.name LIKE :phrase $database->escape OR m_$section.address LIKE :phrase $database->escape";
			}

			$base = "{$database->prefix}mail as mail$target LEFT JOIN {$database->prefix}attachment as attachment ON mail.id = attachment.mail
			WHERE mail.user = :user AND (mail.subject LIKE :phrase $database->escape$filter OR attachment.name LIKE :phrase $database->escape OR mail.plain LIKE :phrase $database->escape)";

			$param = array(':user' => $user->id, ':phrase' => '%'.$system->database_escape($phrase).'%');

			$query = $database->prepare("SELECT count(mail.id) FROM $base");
			$query->run($param);

			if(!$query->success) return false;
			$this->count = $query->column(); #Get total count

			$paging = Search_1_0_0_Item::limit($limit, $page); #Result limit
			$query = $database->prepare("SELECT mail.id, mail.subject, mail.sent, m_from.name, m_from.address FROM $base ORDER BY mail.sent DESC $paging");

			$query->run($param);
			if(!$query->success) return false;

			foreach($query->all() as $row) $this->result['item'][] = array('id' => $row['id'], 'text' => $row['subject'], 'date' => $row['sent'], 'from' => $row['address'], 'name' => $row['name']);
		}
	}
?>

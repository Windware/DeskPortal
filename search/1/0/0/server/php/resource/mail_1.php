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

			$this->count = 0;
			$this->result['item'] = array();
		}
	}
?>

<?php
	class Search_1_0_0_Resource_Headline_1
	{
		public $count = 0;

		public $result = array();

		protected function _quit() #Quit function on error
		{
			$this->count = 0;
			$this->result = array();

			return false;
		}

		public function __construct($phrase, $limit, $page, System_1_0_0_User $user = null)
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$name = explode('_', __CLASS__);
			$database = $system->database('user', __METHOD__, $user, strtolower($name[5]), $name[6]);

			if(!$database->success) return false;

			#Search for all subscribed feeds
			$query = $database->prepare("SELECT feed FROM {$database->prefix}subscribed WHERE user = :user");
			$query->run(array(':user' => $user->id));

			if(!$query->success) return false;

			$sub = array(); #List of subscription
			foreach($query->all() as $row) $sub[] = $row['feed'];

			#NOTE : Not searching for categories as the interface provides it and could clutter the result

			$database = $system->database('system', __METHOD__, null, strtolower($name[5]), $name[6]);
			if(!$database->success) return false;

			$param = array();
			$value = array(':phrase' => '%'.$system->database_escape($phrase).'%');

			foreach($sub as $index => $id)
			{
				$param[] = "feed = :id{$index}_index";
				$value[":id{$index}_index"] = $id;
			}

			$base = "FROM {$database->prefix}entry WHERE (".implode(' OR ', $param).") AND (subject LIKE :phrase OR section LIKE :phrase OR description LIKE :phrase)";

			$query = $database->prepare("SELECT COUNT(id) $base"); #Count the results
			$query->run($value);

			if(!$query->success) return $this->_quit();

			$this->count = $query->column();
			$paging = Search_1_0_0_Item::limit($limit, $page); #Result limit

			$query = $database->prepare("SELECT id, date, subject $base ORDER BY date DESC $paging"); #Look for entries with given phrase inside
			$query->run($value);

			if(!$query->success) return $this->_quit();

			foreach($query->all() as $row)
				$this->result['item'][] = array('id' => $row['id'], 'date' => preg_replace('/ .+/', '', $row['date']), 'text' => $row['subject']);
		}
	}
?>

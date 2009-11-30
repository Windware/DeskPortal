<?php
	class Search_1_0_0_Resource_Todo_1
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

			$base = "FROM {$database->prefix}todo as todo LEFT JOIN {$database->prefix}category as cat ON todo.user = :user AND todo.category = cat.id AND cat.user = :user AND (todo.title LIKE :phrase OR todo.content LIKE :phrase OR cat.name LIKE :phrase)";
			$value = array(':user' => $user->id, ':phrase' => "%$phrase%");

			$query = $database->prepare("SELECT COUNT(id) $base"); #Look for matches in category names, entry title or entry content
			$query->run($value);

			if(!$query->success) return false;

			$this->count = $query->column();
			$paging = Search_1_0_0_Item::limit($limit, $page); #Result limit

			$query = $database->prepare("SELECT todo.id, todo.title $base $paging");
			$query->run($value);

			if(!$query->success) return false;
			foreach($query->all() as $row) $this->result['item'][] = array('id' => $row['id'], 'text' => $row['title']);
		}
	}
?>

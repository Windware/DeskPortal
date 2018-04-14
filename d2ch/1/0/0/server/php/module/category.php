<?php

class D2ch_1_0_0_Category
{
	public static function get() #Get list of categories
	{
		$system = new System_1_0_0(__FILE__);

		$database = $system->database('system', __METHOD__);
		if(!$database->success) return false;

		$query = $database->prepare("SELECT * FROM {$database->prefix}category ORDER BY id");
		if(!$query->run()) return false;

		foreach($query->all() as $row) $list[$row['id']] = $row['name'];
		return $list;
	}
}

?>

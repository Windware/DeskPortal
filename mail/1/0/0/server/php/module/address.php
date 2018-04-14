<?php

class Mail_1_0_0_Address
{
	public static function group(System_1_0_0_User $user = null)
	{
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		if($user === null) $user = $system->user();
		if(!$user->valid) return false;

		$database = $system->database('user', __METHOD__, $user, 'addressbook', 1);
		if(!$database->success) return false;

		$query = $database->prepare("SELECT id, name FROM {$database->prefix}groups WHERE user = :user");
		$query->run([':user' => $user->id]);

		return $query->success ? $query->all() : false;
	}

	public static function load($group, $type, System_1_0_0_User $user = null) #Load addresses from addressbook database with mail addresses
	{
		$system = new System_1_0_0(__FILE__);

		$log = $system->log(__METHOD__);
		if(!$system->is_digit($group) || !in_array($type, ['main', 'alt', 'mobile'])) return $log->param();

		if($user === null) $user = $system->user();
		if(!$user->valid) return false;

		$database = $system->database('user', __METHOD__, $user, 'addressbook', 1);
		if(!$database->success) return false;

		$query = $database->prepare("SELECT name, mail_$type FROM {$database->prefix}address WHERE user = :user AND groups = :groups AND mail_$type != :empty AND mail_$type IS NOT NULL ORDER BY name");
		$query->run([':user' => $user->id, ':groups' => $group, ':empty' => '']);

		return $query->success ? $query->all() : false;
	}
}

?>

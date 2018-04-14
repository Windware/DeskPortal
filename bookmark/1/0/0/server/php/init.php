<?php

class Bookmark_1_0_0
{
	public static function update() #Updates all the address statuses. Run by periodic scheduler
	{ #TODO - Not used in this version
		return; #NOTE : Not implemented in this version

		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		$database = $system->database('system', __METHOD__);
		if(!$database->success) return false;

		$query = $database->prepare("SELECT * FROM {$database->prefix}address WHERE checked <= :date");
		$query->run([':date' => $system->date_datetime(time() - 24 * 3600)]); #Pick entries not checked in the last 24 hours

		if(!$query->success) return false;
		#TODO - Check the number of reference to the bookmark and if no one is using it, delete it or skip it
		$pool = $target = $id = []; #List of connections, addresses and address ID

		foreach($query->all() as $row)
		{
			$target[] = ['address' => $row['address'], 'method' => 'HEAD'];
			$id[$row['address']] = $row['id'];
		}

		$result = $system->network_http($target);
		if($result === false) return false;

		#Prepare the update statement
		$query = $database->prepare("UPDATE {$database->prefix}address SET type = :type, status = :status, checked = :checked WHERE id = :id");

		foreach($result as $content) #Update the address information ignoring any partial errors
			$query->run([':type' => $content['header']['content-type'], ':status' => $content['status'], ':checked' => $system->date_datetime(), ':id' => $id[$content['address']]]);
	}
}

?>

<?php

class D2ch_1_0_0_Message
{
	protected static $_max = 1000; #Max KB to allow downloading as a thread dat file

	protected static $_wait = 5; #Minutes to wait between updating list of messages of a thread

	public static function get($thread) #get the message listing of a thread
	{
		$system = new System_1_0_0(__FILE__);
		if(!$system->is_digit($thread)) return false;

		$database = $system->database('system', __METHOD__);
		if(!$database->success) return false;

		$query = $database->prepare("SELECT updated FROM {$database->prefix}thread WHERE id = :id");
		if(!$query->run([':id' => $thread])) return false;

		$updated = $query->column();
		if(!$updated || strtotime($updated) + self::$_wait * 60 < time()) self::update($thread); #Update the listing if old

		$query = $database->prepare("SELECT * FROM {$database->prefix}message WHERE thread = :thread ORDER BY number");
		if(!$query->run([':thread' => $thread])) return false;

		return $query->all();
	}

	public static function update($thread) #Update the message listing of a thread
	{
		$system = new System_1_0_0(__FILE__);
		if(!$system->is_digit($thread)) return false;

		$database = $system->database('system', __METHOD__);
		if(!$database->success) return false;

		$query = $database->prepare("SELECT address, file FROM {$database->prefix}thread as thread, {$database->prefix}board as board WHERE thread.id = :id AND thread.board = board.id");
		if(!$query->run([':id' => $thread])) return false;

		$row = $query->row();

		$source = $system->network_http([['address' => "{$row['address']}/dat/{$row['file']}", 'max' => self::$_max * 1000]]); #Get subject list
		if($source[0]['status'] != 200) return false;

		if(!$database->begin()) return false;

		$query = $database->prepare("SELECT MAX(number) FROM {$database->prefix}message WHERE thread = :thread"); #Get max number post on a thread
		if(!$query->run([':thread' => $thread])) return false;

		$source[0]['body'] = mb_convert_encoding($source[0]['body'], 'utf-8', mb_detect_encoding($source[0]['body'])); #Use UTF-8

		$index = $query->column();
		if(!$index) $index = 0;

		$query = $database->prepare("INSERT INTO {$database->prefix}message (thread, number, name, mail, signature, posted, message) VALUES (:thread, :number, :name, :mail, :signature, :posted, :message)");

		foreach(array_slice(explode("\n", $source[0]['body']), $index) as $line) #Pick the non recorded messages
		{
			list($name, $mail, $time, $message) = $content = explode('<>', rtrim($line), 5);
			if(count($content) < 4) continue;

			if(strstr($time, ' ID:')) list($time, $signature) = explode(' ID:', $time); #If ID exists for this thread, get it
			else $signature = null;

			preg_match('|(\d+)/(\d+)/(\d+)\(.+?\) (\d+):(\d+):(\d+)|', $time, $match); #Parse the date
			$time = "{$match[1]}-{$match[2]}-{$match[3]} {$match[4]}:{$match[5]}:{$match[6]}";

			$insert = [':thread' => $thread, ':number' => ++$index, ':name' => $name, ':mail' => $mail, ':signature' => $signature, ':posted' => $time, ':message' => $message];
			if(!$query->run($insert)) return $database->rollback() && false;
		}

		$query = $database->prepare("UPDATE {$database->prefix}thread SET updated = :updated WHERE id = :id");
		if(!$query->run([':updated' => $system->date_datetime(), ':id' => $thread])) return $database->rollback && false;

		return $database->commit() || $database->rollback() && false;
	}
}

?>

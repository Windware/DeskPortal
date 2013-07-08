<?php
	class D2ch_1_0_0_Thread
	{
		protected static $_max = 200; #Max KB to get for subject list

		protected static $_wait = 5; #Minutes to wait between updating list of threads of a board

		public static function get($board) #Get list of threads
		{
			$system = new System_1_0_0(__FILE__);
			if(!$system->is_digit($board)) return false;

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT updated FROM {$database->prefix}board WHERE id = :id");
			if(!$query->run(array(':id' => $board))) return false;

			$updated = $query->column();
			if(!$updated || strtotime($updated) + self::$_wait * 60 < time()) self::update($board); #Update the listing if old

			$query = $database->prepare("SELECT * FROM {$database->prefix}thread WHERE board = :board ORDER BY number");
			if(!$query->run(array(':board' => $board))) return false;

			return $query->all();
		}

		public static function update($board) #Update list of threads
		{
			$system = new System_1_0_0(__FILE__);
			if(!$system->is_digit($board)) return false;

			$database = $system->database('system', __METHOD__);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT address FROM {$database->prefix}board WHERE id = :id");
			if(!$query->run(array(':id' => $board))) return false;

			$source = $system->network_http(array(array('address' => $query->column().'/subject.txt', 'max' => self::$_max * 1000))); #Get subject list
			if($source[0]['status'] != 200) return false;

			$query = $database->prepare("SELECT file, updated FROM {$database->prefix}thread WHERE board = :board"); #Keep the updated time
			if(!$query->run(array(':board' => $board))) return false;

			foreach($query->all() as $row) $updated[$row['id']] = $row['updated'];
			if(!$database->begin()) return false;

			$query = $database->prepare("DELETE FROM {$database->prefix}thread WHERE board = :board"); #Replace all listing
			if(!$query->run(array(':board' => $board))) return false;

			$query = $database->prepare("INSERT INTO {$database->prefix}thread (board, file, number, reply, title, updated) VALUES (:board, :file, :number, :reply, :title, :updated)"); #Replace all listing
			$index = 0; #Thread number

			$source[0]['body'] = mb_convert_encoding($source[0]['body'], 'utf-8', mb_detect_encoding($source[0]['body'])); #Use UTF-8

			foreach(explode("\n", $source[0]['body']) as $line)
			{
				preg_match('/^(.+?\.dat)<>(.+?)( \((\d+)\))?$/', $line, $match);
				list(, $item, $title, , $reply) = $match; #Separate the file name, the title and reply count

				if(!$item || !$title) continue;
				if(!$query->run(array(':board' => $board, ':file' => $item, ':number' => ++$index, ':reply' => $reply, ':title' => $title, ':updated' => $updated[$item]))) return $database->rollback() && false;
			}

			$query = $database->prepare("UPDATE {$database->prefix}board SET updated = :updated WHERE id = :id");
			if(!$query->run(array(':updated' => $system->date_datetime(), ':id' => $board))) return $database->rollback && false;

			return $database->commit() || $database->rollback() && false;
		}
	}
?>

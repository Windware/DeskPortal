<?php

class Bookmark_1_0_0_Cache
{
	protected static $_attributes = 'style [a-z]?link bgcolor color size face background class id on[a-z]+'; #List of HTML attributes separated by space to remove from textual cache

	protected static $_solo = 'base img link'; #HTML elements to remove for solo tags (Workaround on PHP 5.2.6 bug that returns empty string on preg_replace)

	protected static $_surround = 'script style noscript map object embed iframe option select source video'; #List of HTML elements to remove from cache (For surrounding tags)

	public static $_max = 200; #Max size in KB to be allowed as a page cache (Without external files and before tag stripping and compressing)

	public static function add($id, System_1_0_0_User $user = null) #Create a cache of a bookmark
	{
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		if(!$system->is_digit($id)) return $log->param();

		if($user === null) $user = $system->user();
		if(!$user->valid) return 1;

		$database = $system->database('system', __METHOD__);
		if(!$database->success) return 1;

		$query = $database->prepare("SELECT address FROM {$database->prefix}address WHERE id = :id");
		$query->run([':id' => $id]); #Get the address of the bookmark

		if(!$query->success) return 1;

		$address = $query->column();
		if(!$system->is_address($address)) return 1;

		$database = $system->database('user', __METHOD__, $user);
		if(!$database->success) return 1;

		#Get the actual data from remote server (Pass maximum possible file size to retrieve)
		$request = $system->network_http([['address' => $address, 'max' => self::$_max * 1000]]);

		if($request[0]['status'] != 200) return 1; #If the request is not found, quit
		if($request[0]['exceed']) return 2; #If the response is too big, quit

		$page = $request[0]['body']; #The content body

		if(preg_match('|^text/html\b|i', $request[0]['header']['content-type'])) #Strip some tags for a HTML response
		{
			$match = str_replace(' ', '|', self::$_surround);
			$page = preg_replace("!<($match)\b[^>]*>.*?</\\1>!si", '', $page); #Remove the specified surrounding tags

			#NOTE : Separating these for PHP bug (as of 5.2.6) to return an empty string if mixed with the above replacement
			$match = str_replace(' ', '|', self::$_solo);
			$page = preg_replace("/<($match)\b.*?>/si", '', $page); #Remove the specified solo tags

			#Remove redundant white spaces, comments and specified attributes
			$match = str_replace(' ', '|', self::$_attributes); #List of attributes to remove
			$page = preg_replace(['/\s{2,}/', '/<!--(.+?)-->/s', "/\s+($match)\s*=\s*(([\"']).*?\\3|[^\s>]+)/"], [' ', ''], $page);

			$adjustments = $system->file_read("{$system->self['root']}resource/adjustment.html");
			if(!$adjustments) return 1;

			#Insert link adjustment and styles
			$page = preg_replace('/<head.*?>/i', '<head>' . str_replace('%address%', $address, $adjustments), $page);
		}

		$query = $database->prepare("REPLACE INTO {$database->prefix}cache (user, bookmark, file, time, type, content) VALUES (:user, :bookmark, :file, :time, :type, :content)");
		$query->run([':user' => $user->id, ':bookmark' => $id, ':file' => $address, ':time' => $system->date_datetime(), ':type' => $request[0]['header']['content-type'], ':content' => $system->compress_build($page)]);

		return $query->success ? 0 : 1;
	}

	public static function get($id, System_1_0_0_User $user = null) #Get the bookmark cache content
	{ #TODO - Data itself should be saved as files in raw and gzipped format
		$system = new System_1_0_0(__FILE__);
		$log = $system->log(__METHOD__);

		if(!$system->is_digit($id)) return $log->param();

		if($user === null) $user = $system->user();
		if(!$user->valid) return false;

		$database = $system->database('user', __METHOD__, $user);
		if(!$database->success) return false;

		$query = $database->prepare("SELECT bookmark, file, time, content, type FROM {$database->prefix}cache WHERE user = :user AND bookmark = :bookmark");
		$query->run([':bookmark' => $id, ':user' => $user->id]); #Get the address of the bookmark

		return $query->success ? $query->row() : false;
	}
}

?>

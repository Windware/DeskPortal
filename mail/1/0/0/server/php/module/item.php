<?php
	class Mail_1_0_0_Item #Supports both POP3/IMAP and servers without UIDL or UID support
	{ #TODO : Include PEAR packages as local files to avoid code change and to minimize external dep - Auth_SASL, Net_POP3, Net_SMTP, Mail, Mail_mime
		protected static $_attachment = 50; #Maximum megabytes allowed to be sent as an entire mail

		protected static $_flag = array('deleted' => 'Deleted', 'seen' => 'Seen', 'marked' => 'Flagged', 'replied' => 'Answered', 'draft' => 'Draft'); #Table column names and corresponding IMAP flags

		protected static $_max = 200; #Max kilobytes allowed to be transferred from mail servers as the message body. No body will be retrieved if it is bigger.

		protected static $_page = 50; #Default number of mails to display per page

		#Amount of max (bytes, lines) of a message body preview to be sent with the list of mails. Any message bodies that are larger will be truncated to the size and lines for previews
		protected static $_preview = array(500, 5);

		protected static $_storage = array('drafts' => 'Drafts', 'sent' => 'Sent'); #Generic name for draft and sent special folders

		protected static $_type = array('text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'); #Mime categories (Order is important)

		private static $_connection = array(); #POP3 connection cache for manual connection instead of using 'imap_' functions

		private static $_structure; #Mime structure for each mails

		public static $_limit = 500; #Max number of place holders possible in database queries

		protected static function _dig($parts, $position = '') #Dig through the multi part message
		{
			if(!is_array($parts)) return false;

			if($position) $position = "$position.";
			foreach($parts as $index => $section) self::_dig($section->parts, $position.++$index); #If inner parts exist, check them

			foreach($parts as $index => $section) #Pick the position in the structure
			{
				$name = null; #Attachment file names - NOTE : Decoding rule from RFC2231

				if(is_array($section->dparameters)) #If attachment parameters are present
				{
					$file = array();

					foreach($section->dparameters as $param) #Concatenate multi line file names
						if(preg_match('/^filename(\*(\d+)(\*?))?$/i', $param->attribute, $matches)) $file[$matches[2]] = $param->value;

					ksort($file);
					$file = explode("'", rawurldecode(implode('', $file)), 3); #Separate the character encoding strings

					if(count($file) == 3) $file = mb_convert_encoding($file[2], 'utf-8', mb_detect_encoding($file[2])); #Try to convert (Ignore what is set but detect manually)
					else $file = $file[0]; #If non set, use the whole string

					foreach(imap_mime_header_decode($file) as $value) $name .= mb_convert_encoding($value->text, 'utf-8', mb_detect_encoding($value->text));
				}

				$mime = self::$_type[$section->type].'/'.strtolower($section->subtype);
				switch($mime) { case 'image/jpg' : $mime = 'image/jpeg'; break; } #Fix bad mime type

				$data = self::$_structure[] = array('name' => $name, 'position' => $position.($index + 1), 'size' => $section->bytes, 'structure' => $section, 'type' => $mime);
			}
		}

		public static function _pop3(&$system, $host, $port, $secure, $name, $pass, $user) #Connect to a POP3 server
		{
			if(isset(self::$_connection[$user->id]["$host:$port"])) return self::$_connection["$host:$port"];

			self::$_connection[$user->id]["$host:$port"] = false;
			if(!$system->file_load('Auth/SASL.php') || !$system->file_load('Net/POP3.php')) return false;

			$secure = $secure ? 'ssl://' : ''; #Specify SSL connection if configured so
			self::$_connection[$user->id]["$host:$port"] =& new Net_POP3();

			$connect = self::$_connection[$user->id]["$host:$port"]->connect($secure.$host, $port); #Connect
			if($connect !== true) return false;

			$connect = self::$_connection[$user->id]["$host:$port"]->login($name, $pass); #Login
			return $connect === true ? self::$_connection[$user->id]["$host:$port"] : false;
		}

		public static function attachment($id, $mode = 'attachment', System_1_0_0_User $user = null) #Get and send the attachment back
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id) || $mode != 'attachment' && $mode != 'inline') return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT at.name, at.position, mail.id as mail, mail.folder FROM {$database->prefix}attachment as at, {$database->prefix}mail as mail WHERE at.id = :id AND at.mail = mail.id AND mail.user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id));

			if(!$query->success) return false;
			$attachment = $query->row();

			$query = $database->prepare("SELECT account.id FROM {$database->prefix}folder as folder, {$database->prefix}account as account WHERE folder.id = :id AND folder.user = :user AND folder.account = account.id");
			$query->run(array(':id' => $attachment['folder'], ':user' => $user->id));

			if(!$query->success) return false;

			list($link, $sequence) = self::find(array($attachment['mail']), $user);
			if(!$link || !is_array($sequence)) return false;

			$structure = imap_bodystruct($link['connection'], $sequence[0], $attachment['position']); #Get the attachment information
			$body = imap_fetchbody($link['connection'], $sequence[0], $attachment['position']);#Get the attachment data

			switch($structure->encoding) #Decode encoded string
			{
				case 3 : $body = base64_decode($body); break;

				case 4 : $body = quoted_printable_decode($body); break;
			}

			#Foe IE, URL encode the file name to avoid file name getting corrupted using UTF-8
			$name = strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE') ? rawurlencode($attachment['name']) : str_replace('"', '\\"', $attachment['name']);

			$mime = self::$_type[$structure->type].'/'.strtolower($structure->subtype);
			switch($mime) { case 'image/jpg' : $mime = 'image/jpeg'; break; } #Fix bad mime type

			$system->cache_header(); #Cache attachment data on the client side

			header("Content-Type: $mime");
			header("Content-Disposition: attachment; filename=\"$name\"");

			header('Content-Length: '.strlen($body));
			return $body;
		}

		#NOTE : 'imap_utf8' turns all letters into capital letters : http://bugs.php.net/bug.php?id=44098
		public static function decode($string) #Turn mime strings into UTF8 strings (Cloning imap_utf8 function)
		{
			if(!is_string($string)) return '';

			#Parse the encoded string - NOTE : Detecting the encoding manually since some mails put false encoding information
			foreach(imap_mime_header_decode($string) as $value) $decoded .= mb_convert_encoding($value->text, 'utf-8', mb_detect_encoding($value->text));

			$encoding = mb_detect_encoding($decoded); #Check if it is properly converted
			return $encoding == 'ASCII' || $encoding == 'UTF-8' ? $decoded : '(?)'; #Avoid other character sets to avoid outputting XML from corrupting
		}

		#NOTE - Only allows specifying mail ID under a same folder or returns false
		public static function find($list, System_1_0_0_User $user = null) #Find sequence number on the mail server for mails by ID
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!is_array($list)) return $log->param();
			if(!count($list)) return array(array(), array());

			foreach($list as $id) if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$target = array();
			$param = array(':user' => $user->id);

			foreach($list as $index => $id)
			{
				if(!$system->is_digit($index)) continue;

				$value = $target[] = ":i{$index}d";
				$param[$value] = $id;
			}

			if(!count($target)) return false;
			$target = implode(', ', $target);

			$query = $database->prepare("SELECT id, folder, uid, signature FROM {$database->prefix}mail WHERE id IN ($target) AND user = :user");
			$query->run($param);

			if(!$query->success) return false;
			if(!count($collection = $query->all())) return array(null, null); #When no matching mail is found

			foreach($collection as $identity)
			{
				if(isset($folder) && $folder != $identity['folder']) #Only allow mail ID of a single folder
					return $log->dev(LOG_WARNING, 'Cannot specify mail ID spanning across other accounts and folders', 'Specify list of mail ID belonging to a single folder');

				$folder = $identity['folder'];
			}

			$query = $database->prepare("SELECT account FROM {$database->prefix}folder WHERE id = :id AND user = :user");
			$query->run(array(':id' => $folder, ':user' => $user->id));

			if(!$query->success) return false;

			$link = Mail_1_0_0_Account::connect($query->column(), $folder, $user); #Connect to the server (TODO - Avoid connecting here for POP3)
			if(!$link) return false;

			$numbers = array(); #Returning sequence numbers

			if($link['info']['supported']) #If UID is available
			{
				if($link['type'] == 'imap')
				{
					$list = imap_search($link['connection'], 'ALL', SE_UID);
					if(is_array($list)) foreach($list as $index => $id) $uid[$id] = $index + 1; #Get unique ID list
				}
				elseif($link['type'] == 'pop3') #List the unique ID and find the message number for POP3
				{
					$connection = Mail_1_0_0_Account::_special($link['info']);

					$pop3 = self::_pop3($system, $connection['receiver']['host'], $connection['receiver']['port'], $connection['receiver']['secure'], $connection['receiver']['user'], $connection['receiver']['pass'], $user);
					if(!$pop3) return false;

					if(!is_array($listing = $pop3->getListing())) return false; #Get all listing
					foreach($listing as $list) $uid[$list['uidl']] = $list['msg_id'];
				}

				foreach($collection as $identity) if($system->is_digit($uid[$identity['uid']])) $numbers[] = $uid[$identity['uid']];
			}
			else #When UID/UIDL is not available
			{
				$content = imap_check($link['connection']);
				if(!is_object($content)) return Mail_1_0_0_Account::error($link['host']);

				$compare = array(); #List of mail signatures on the mail server

				for($sequence = 1; $sequence <= $content->Nmsgs; $sequence++) #Go through entire messages
				{
					$signature = imap_fetchheader($link['connection'], $sequence); #Signature of a message
					if(!$signature) return Mail_1_0_0_Account::error($link['host']);

					$compare[md5($signature)] = $sequence; #Gather mail signatures
				}

				foreach($collection as $identity) if($compare[$identity['signature']]) $numbers[] = $compare[$identity['signature']]; #Check the signatures in the database
			}

			return array($link, $numbers);
		}

		public static function flag($list, $flag, $mode, System_1_0_0_User $user = null) #Flag mails
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!is_array($list) || !in_array($flag, self::$_flag)) return $log->param();
			if(!count($list)) return true;

			foreach($list as $id) if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			if($flag != 'Deleted') #Nothing to flag for 'Deleted' flag to the database
			{
				foreach(self::$_flag as $column => $mark) if($mark == $flag) $set = $column; #Find the column name for the flag

				for($i = 0; $i < count($list); $i += self::$_limit) #Split into flagging limit count ID at once to avoid place holder limitation
				{
					$target = array();
					$param = array(':mode' => $mode ? 1 : 0, ':user' => $user->id);

					foreach(array_slice($list, $i, self::$_limit) as $index => $id)
					{
						if(!$system->is_digit($index)) continue;

						$value = $target[] = ":i{$index}d"; #NOTE : Having the place holder as ':id[DIGIT]' confuses in cases like ':id1' and ':id10'
						$param[$value] = $id;
					}

					if(!count($target)) continue;
					$target = implode(', ', $target);

					$query = $database->prepare("UPDATE {$database->prefix}mail SET $set = :mode WHERE id IN ($target) AND user = :user");
					$query->run($param);

					if(!$query->success) return false;
				}
			}

			$query = $database->prepare("SELECT receive_type FROM {$database->prefix}mail as mail, {$database->prefix}folder as folder, {$database->prefix}account as account WHERE mail.id = :id AND mail.user = :user AND mail.folder = folder.id AND folder.account = account.id");
			$query->run(array(':id' => $list[0], ':user' => $user->id)); #Pick the account type the mail belongs to

			if(!$query->success) return false;
			if(Mail_1_0_0_Account::type($query->column()) != 'imap') return true; #Only flag on server for IMAP

			list($link, $sequence) = self::find($list, $user); #Find the sequence number for the mail ID
			if(!$link || !is_array($sequence)) return false;

			if(!count($sequence)) return true;

			#Change the flagged state #TODO - Any max number of list to send at once?
			if($mode) $op = imap_setflag_full($link['connection'], implode(',', $sequence), "\\$flag");
			else $op = imap_clearflag_full($link['connection'], implode(',', $sequence), "\\$flag");

			if(!$op) return Mail_1_0_0_Account::error($link['host']);

			if($flag == 'Deleted') #Remove the flagged mails
			{
				$op = imap_expunge($link['connection']); #Delete the mails permanently
				if(!$op) return Mail_1_0_0_Account::error($link['host']);
			}

			return true;
		}

		public static function folder($id, System_1_0_0_User $user = null) #Get folder and account the mail belongs to (TODO - Allow specifying the sort order)
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT account.id as account, mail.folder FROM {$database->prefix}mail as mail, {$database->prefix}folder as folder, {$database->prefix}account as account
			WHERE mail.id = :id AND mail.user = :user AND mail.folder = folder.id AND folder.user = :user AND folder.account = account.id AND account.user = :user");

			$query->run(array(':id' => $id, ':user' => $user->id));
			if(!$query->success) return false;

			$relation = $query->row();

			$query = $database->prepare("SELECT id FROM {$database->prefix}mail WHERE user = :user AND folder = :folder ORDER BY sent DESC");
			$query->run(array(':user' => $user->id, ':folder' => $relation['folder'])); #Get entire list of ID to find on which page the mail belongs to

			if(!$query->success) return false;

			foreach($query->all() as $index => $row)
			{
				if($row['id'] != $id) continue;

				$position = $index;
				break;
			}

			$relation['page'] = floor($position / self::$_page) + 1; #Find on which page the mail belongs to
			return $relation;
		}

		public static function get($folder, $page, $order = 'sent', $reverse = true, $marked = false, $unread = false, $search = '', $amount = null, System_1_0_0_User $user = null) #Get list of mails
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($folder) || !$system->is_digit($page) || preg_match('/\W/', $order)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$param = array(':user' => $user->id, ':folder' => $folder);

			if($marked)
			{
				$filter .= ' AND marked = :marked';
				$param[':marked'] = 1;
			}

			if($unread)
			{
				$filter .= ' AND seen = :seen';
				$param[':seen'] = 0;
			}

			switch($order) #Join the extra table for address sorting
			{
				case 'from' : case 'to' : case 'cc' : case 'bcc' :
					$foreign = " LEFT JOIN {$database->prefix}$order as _$order ON id = _$order.mail";
					$order = 'address'; #Specify the column name for the address to sort
				break;
			}

			if(is_string($search) && strlen($search) > 1)
			{
				foreach(array('from', 'to', 'cc', 'bcc') as $target)
				{
					if($order != $target) $foreign .= " LEFT JOIN {$database->prefix}$target as _$target ON id = _$target.mail";
					$limiter .= " OR _$target.name LIKE :search $database->escape OR _$target.address LIKE :search $database->escape";
				}

				$filter .= " AND (subject LIKE :search $database->escape$limiter)";
				$param[':search'] = '%'.$system->database_escape($search).'%';
			}

			$order = "LOWER($order)"; #NOTE : Using 'LOWER' for case insensitive sorting to be compatible across database engines, possibly FIXME for performance
			if(!$system->is_digit($amount)) $amount = self::$_page; #Set to default amount of mails per page

			$reverse = $reverse ? ' DESC' : '';
			$start = ($page - 1) * $amount;

			$query['account'] = $database->prepare("SELECT account FROM {$database->prefix}folder WHERE id = :id AND user = :user");
			$query['account']->run(array(':id' => $folder, ':user' => $user->id));

			if(!$query['account']->success) return false;
			$account = $query['account']->column();

			$query['count'] = $database->prepare("SELECT count(id) FROM {$database->prefix}mail$foreign WHERE user = :user AND folder = :folder$filter");
			$query['count']->run($param);

			if(!$query['count']->success) return false;

			$total = $query['count']->column();
			if(!$total) $total = 0;

			$query['all'] = $database->prepare("SELECT id, subject, sent, received, preview, draft, marked, seen, replied FROM {$database->prefix}mail$foreign WHERE user = :user AND folder = :folder$filter ORDER BY $order$reverse LIMIT $start,$amount");
			$query['all']->run($param);

			if(!$query['all']->success) return false;

			$all = $query['all']->all();
			$mail = $target = $param = array();

			foreach($all as $index => $row)
			{
				$value = $target[] = ":i{$index}d";
				$param[$value] = $row['id'];
			}

			if(count($target))
			{
				$target = implode(', ', $target);

				foreach(array('from', 'to', 'cc', 'bcc') as $section)
				{
					$query['address'] = $database->prepare("SELECT mail, name, address FROM {$database->prefix}$section WHERE mail IN ($target)");
					$query['address']->run($param);

					if(!$query['address']->success) return false;
					foreach($query['address']->all() as $row) $mail[$row['mail']][$section][] = array('name' => $row['name'], 'address' => $row['address']);
				}

				$query['attachment'] = $database->prepare("SELECT id, mail, name, size, type FROM {$database->prefix}attachment WHERE mail IN ($target) AND name != ''");
				$query['attachment']->run($param);

				if(!$query['attachment']->success) return false;
				foreach($query['attachment']->all() as $row) $mail[$row['mail']]['attachment'][] = array('id' => $row['id'], 'cid' => $row['cid'], 'name' => $row['name'], 'size' => $row['size'], 'type' => $row['type']);
			}

			$list = array();

			foreach($all as $row)
			{
				foreach(array('from', 'to', 'cc', 'bcc', 'attachment') as $section) #Construct the mail address and attachment lists
					if(is_array($mail[$row['id']][$section])) foreach($mail[$row['id']][$section] as $line) $row[$section][] = $line;

				$row['account'] = $account;
				$list[] = $row;
			}

			return array('list' => $list, 'page' => floor($total / ($amount + 1)) + 1);
		}

		public static function image($id, $cid) #Get the embedded image data
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id) || !$system->is_text($cid)) return $log->param();
			if(strlen($cid) > 255) return false;

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT at.id FROM {$database->prefix}attachment as at, {$database->prefix}mail as mail WHERE at.mail = :id AND at.cid = :cid AND at.mail = mail.id AND mail.user = :user");
			$query->run(array(':id' => $id, ':cid' => $cid, ':user' => $user->id));

			if(!$query->success) return false;
			return self::attachment($query->column(), 'inline', $user);
		}

		public static function move($list, $folder, $copy = false, System_1_0_0_User $user = null) #Move or copy mails to another folder
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!is_array($list) || !$system->is_digit($folder)) return $log->param();
			if(!count($list)) return true;

			foreach($list as $id) if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT receive_type, account.id FROM {$database->prefix}folder as folder, {$database->prefix}account as account WHERE folder.id = :id AND folder.user = :user AND folder.account = account.id AND account.user = :user");
			$query->run(array(':id' => $folder, ':user' => $user->id));

			if(!$query->success) return false;
			$account = $query->row();

			$type = Mail_1_0_0_Account::type($account['receive_type']);

			if($type == 'imap') #For IMAP, move the mails on the server - TODO : Need different code for cross account move. It now breaks when source account and target account differs.
			{
				$query = $database->prepare("SELECT folder.id, folder.name, mail.uid FROM {$database->prefix}mail as mail, {$database->prefix}folder as folder WHERE mail.id = :id AND mail.user = :user AND mail.folder = folder.id AND folder.user = :user");
				$query->run(array(':id' => $list[0], ':user' => $user->id)); #Get folder name from the first mail item (If another mail belongs to another folder, self::find will fail)

				if(!$query->success) return false;

				$current = $query->row(); #Current folder ID
				if($current['id'] == $folder) return true; #If the target is the same folder, quit

				$target = mb_convert_encoding(Mail_1_0_0_Folder::name($folder, $user), 'UTF7-IMAP', 'UTF-8'); #Target folder name
				if(!$system->is_text($target)) return false;

				list($link, $sequence) = self::find($list, $user); #Get the message numbers for the mails
				if(!$link || !is_array($sequence)) return false;

				if(!count($sequence)) return true;

				$link = Mail_1_0_0_Account::connect($account['id'], $current['id'], $user); #Connect to the target folder first
				if(!$link) return false;

				if(!$copy)
				{
					#Move the messages : TODO - Any limit for max messages to move?
					if(!imap_mail_move($link['connection'], implode(',', $sequence), $target)) return Mail_1_0_0_Account::error($link['host']);
					if(!imap_expunge($link['connection'])) return Mail_1_0_0_Account::error($link['host']); #Clean up the mails after move

					#NOTE : Aquiring the new UID of the moved messages is not easy since they change, thus not keeping the local copies trying to update the folder parameter only
					self::remove($list, true, $user); #Remove mails in the source folder
				}
				else
				{
					$op = imap_mail_copy($link['connection'], implode(',', $sequence), $target); #Copy the messages
					if(!$op) return Mail_1_0_0_Account::error($link['host']);
				}
			}
			else #For POP3, update the local database only
			{
				if(!$copy) #On move
				{
					#NOTE : Not making transaction to keep the result if it fails on later recursion
					for($i = 0; $i < count($list); $i += self::$_limit) #Split into moving limit count ID at once to avoid place holder limitation
					{
						$target = array();
						$param = array(':folder' => $folder, ':user' => $user->id);

						foreach(array_slice($list, $i, self::$_limit) as $index => $id)
						{
							if(!$system->is_digit($index)) continue;

							$value = $target[] = ":i{$index}d";
							$param[$value] = $id;
						}

						if(!count($target)) continue;
						$target = implode(', ', $target);

						$query = $database->prepare("UPDATE {$database->prefix}mail SET folder = :folder WHERE id IN ($target) AND user = :user");
						$query->run($param); #Move to another folder

						if(!$query->success) return false;
					}
				}
				else #On copy
				{
					$column = 'user, uid, signature, subject, mid, mail, sent, received, preview, plain, html, encode, marked, seen, replied, draft, color';

					$query = $database->prepare("INSERT INTO {$database->prefix}mail ($column, folder) SELECT ($column, :folder) FROM {$database->prefix}mail WHERE id = :id AND user = :user");
					$param = array(':folder' => $folder, ':user' => $user->id);

					foreach($list as $id) #Copy each mail to the target folder
					{
						$param[':id'] = $id;
						$query->run($param);

						if(!$query->success) return false;
					}
				}
			}

			return true;
		}

		public static function remove($list, $local = false, System_1_0_0_User $user = null) #Delete mails
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!is_array($list)) return $log->param();
			if(!count($list)) return true;

			foreach($list as $id) if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			if(!$local && !self::flag($list, 'Deleted', true, $user)) return false; #If set to also remove from the mail server, flag and expunge
			if(!$database->begin()) return false; #Make the deletion atomic

			for($i = 0; $i < count($list); $i += self::$_limit) #Split by maximum possible place holder number
			{
				$param = $target = array();

				foreach(array_slice($list, $i, self::$_limit) as $index => $id)
				{
					if(!$system->is_digit($index)) continue;

					$value = $target[] = ":i{$index}d";
					$param[$value] = $id;
				}

				if(!count($target)) continue;
				$target = implode(', ', $target);

				foreach(explode(' ', 'from to cc bcc attachment reference') as $section) #Remove related data
				{
					$query = $database->prepare("DELETE FROM {$database->prefix}$section WHERE mail IN ($target)");
					$query->run($param);

					if(!$query->success) return $database->rollback() && false;
				}

				$query = $database->prepare("DELETE FROM {$database->prefix}reference WHERE reference IN ($target)");
				$query->run($param);

				if(!$query->success) return $database->rollback() && false;
				$param[':user'] = $user->id;

				$query = $database->prepare("DELETE FROM {$database->prefix}mail WHERE id IN ($target) AND user = :user");
				$query->run($param); #Delete the mail entry

				if(!$query->success) return $database->rollback() && false;
			}

			return $database->commit() || $database->rollback() && false;
		}

		#NOTE : Error codes are (0 : success, 1 : system error, 2 : size too big, 3 : SMTP send error, 4 : IMAP store error)
		public static function send($account, $subject, $body, $to, $cc = null, $bcc = null, $source = null, $attachment = array(), $draft = false, $resume = null, System_1_0_0_User $user = null) #Send a mail (Or only save as a draft)
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account) || !is_string($subject) || !is_string($body) || !$system->is_text($to)) return $log->param(1);

			if($user === null) $user = $system->user();
			if(!$user->valid) return 1;

			$total = 0; #Total bytes for mail submission

			foreach(func_get_args() as $index => $param) #Check parameter size and validity
			{
				if($index == 0) continue;

				if(is_string($param)) $total += strlen($param);
				elseif($index <= 6) return $log->param(1); #Check parameter type
			}

			if(is_array($attachment)) foreach($attachment as $file) $total += $file['size']; #Add attachment file sizes
			else $attachment = array();

			if($total > self::$_attachment * 1000 * 1000) return 2; #Avoid large submission to mail servers

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return 1;

			if(!$system->file_load('Mail.php', false, LOG_ERR) || !$system->file_load('Mail/mime.php', false, LOG_ERR)) return 1; #Load the required PEAR packages
			$mime = new Mail_mime(); #Create the mail MIME object

			$mime->setTxtBody($body); #Set the message body
			foreach($attachment as $file) $mime->addAttachment(file_get_contents($file['tmp_name']), $file['type'], $file['name'], false); #Add attachment files if given

			$query = $database->prepare("SELECT * FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $account, ':user' => $user->id));

			if(!$query->success) return 1;

			$row = $query->row();
			$encoded = $list = $toward = array(); #List of encoded addresses, list of addresses and raw mail addresses to send to

			foreach(imap_rfc822_parse_adrlist("{$row['name']} <{$row['address']}>", '') as $parsed)
			{
				if($parsed->host == '.SYNTAX-ERROR.') return 3; #Parse the 'from' addresses

				$address = "{$parsed->mailbox}@{$parsed->host}";
				$list['from'][$address] = $parsed->personal;

				$encoded['from'][] = strlen($parsed->personal) ? mb_encode_mimeheader($parsed->personal)." <$address>" : $address;
			}

			foreach(array('to', 'cc', 'bcc') as $section)
			{
				if(!$system->is_text($$section)) continue;

				foreach(imap_rfc822_parse_adrlist($$section, '') as $parsed) #Parse the addresses
				{
					if($parsed->host == '.SYNTAX-ERROR.') return 3; #Return as SMTP error for invalid addresses

					$address = $toward[] = "{$parsed->mailbox}@{$parsed->host}"; #List of addresses to send to
					$list[$section][$address] = $parsed->personal;

					$encoded[$section][] = strlen($parsed->personal) ? mb_encode_mimeheader($parsed->personal)." <$address>" : $address; #List of addresses to write on the header
				}
			}

			$conf = $system->app_conf();
			$mailer = str_replace(array('%APP%', '%VERSION%'), array($system->self['name'], str_replace('_', '.', $system->self['version'])), $conf['mailer']);

			$header = array( #Headers to go inside the mail header section
				'From' => implode(', ', $encoded['from']),
				'To' => implode(', ', $encoded['to']),
				'Subject' => mb_encode_mimeheader($subject),
				'Date' => $sent = gmdate('r'),
				'Message-ID' => $mid = microtime(true).'-'.md5(mt_rand()).'@'.preg_replace('/^.+@/', '', $row['address']), #Create a unique message ID
				'X-Mailer' => $mailer,
				'Return-Path' => $row['address']
			);

			if(is_array($encoded['cc']) && count($encoded['cc'])) $header['Cc'] = implode(', ', $encoded['cc']); #Add 'Cc' header if it exists
			if($draft && is_array($encoded['bcc']) && count($encoded['bcc'])) $header['Bcc'] = implode(', ', $encoded['bcc']); #Add 'Bcc' header only when saving as a draft

			#NOTE : Do not call these functions ('get' and 'headers') in reverse order (From PEAR manual)
			$mail = array('body' => $mime->get(array('head_charset' => 'utf-8', 'text_charset' => 'utf-8')), 'header' => $mime->headers($header));
			$storage = $draft ? 'drafts' : 'sent'; #The special folder to store the mail to

			if($system->is_digit($row["folder_$storage"]))
			{
				$folder = $row["folder_$storage"];
				$name = Mail_1_0_0_Folder::name($folder, $user); #Find the storage folder
			}

			if(!is_string($name) || !strlen($name)) #If not found
			{
				$name = self::$_storage[$storage];
				$folder = Mail_1_0_0_Folder::create($account, null, $name, $user); #Register a generic folder name

				if(!$folder) return 1;

				$query = $database->prepare("UPDATE {$database->prefix}account SET folder_$storage = :folder WHERE id = :id AND user = :user");
				$query->run(array(':folder' => $folder, ':id' => $account, ':user' => $user->id));

				if(!$query->success) return 1;
			}

			$type = Mail_1_0_0_Account::type($row['receive_type']);

			#NOTE : Periodical mail checking is assumed to pass 'POP before SMTP' restriction without connecting here for POP3
			if($type == 'imap') $link = Mail_1_0_0_Account::connect($account, $folder, $user);

			if(!$draft) #When sending
			{
				$info = Mail_1_0_0_Account::_special($row);

				$connection = array( #SMTP connection parameters - NOTE : TLS is automatically initiated if the mail server supports it
					'host' => ($info['sender']['secure'] ? 'ssl://' : '').$info['sender']['host'],
					'port' => $info['sender']['port'],
					'auth' => strlen($info['sender']['user']) || strlen($info['sender']['pass']),
					'username' => $info['sender']['user'],
					'password' => $info['sender']['pass'],
					'localhost' => $info['sender']['host'],
					'timeout' => $system->app_conf('system', 'static', 'net_timeout'),
					'persist' => false
				);

				$smtp =& Mail::factory('smtp', $connection);
				if($smtp->send($toward, $mail['header'], $mail['body']) !== true) return 3; #SMTP failure
			}

			#NOTE : For this version, attachments are not stored locally at all.
			#For IMAP, the mail will be sent to the server to keep the mail entirely including all attachments and can be reaquired
			#but for POP3, attachments will be discarded when saving the mail locally.
			#
			#In future versions those store attachments locally, they will be stored as files locally named after the ID of the mail
			#and 'position' parameter can be left unused in the database.
			#TODO - IMAP may not need to have attachments saved locally at all at the cost of some download speed or unavailability in case IMAP server is down.

			if($system->is_digit($resume)) Mail_1_0_0_Item::remove(array($resume), false, $user); #If a draft is being saved from a suspended draft, delete the old one. Not stopping if error occurs here.

			if($type == 'pop3') #For POP3, store the mail locally
			{
				if(!$database->begin()) return 1;
				$section = explode(' ', 'user folder subject mid sent preview plain seen draft');

				$column = implode(', ', $section);
				$variable = ':'.implode(', :', $section);

				try
				{
					$query = $database->prepare("INSERT INTO {$database->prefix}mail ($column) VALUES ($variable)");
					$query->run(array(':user' => $user->id, ':folder' => $folder, ':subject' => $subject, ':mid' => $mid, ':sent' => $sent, ':preview' => $preview, ':plain' => $body, ':seen' => 1, ':draft' => $draft ? 1 : 0));

					if(!$query->success || !$system->is_digit($id = $database->id())) throw new Exception();

					foreach(array('from', 'to', 'cc', 'bcc') as $section) #Insert all the addresses in the mail
					{
						foreach($list[$section] as $address => $name)
						{
							$query = $database->prepare("INSERT INTO {$database->prefix}$section (mail, name, address) VALUES (:mail, :name, :address)");
							$query->run(array(':mail' => $id, ':name' => $name, ':address' => $address));

							if(!$query->success) throw new Exception();
						}
					}

					if($database->commit()) return 0;
					throw new Exception();
				}

				catch(Exception $error)
				{
					$database->rollback();
					return 1;
				}
			}

			$header = ''; #Get list of headers to upload to IMAP server
			foreach($mail['header'] as $key => $value) $header .= "$key: $value\r\n";

			if(!imap_append($link['connection'], '{'.$link['host']."}$name", "$header\r\n{$mail['body']}", $draft ? '\\Draft' : '')) #Store the mail on the mail server
			{
				Mail_1_0_0_Account::error($link['host']);
				return 4; #IMAP failure
			}

			if($draft) #If saving a draft, send back the ID of the mail to client to know which older draft to delete on next save
			{
				if(!self::update($folder, $user)) return 1; #Update the folder where the mail was uploaded
				$signature = md5("$header\r\n"); #The mail's header hash

				$query = $database->prepare("SELECT id, header, signature FROM {$database->prefix}mail WHERE user = :user AND folder = :folder AND draft = :draft ORDER BY id DESC");
				$query->run(array(':user' => $user->id, ':folder' => $folder, ':draft' => 1)); #Get list of mails to find the draft

				if(!$query->success) return 1;

				#NOTE : Returning a negative number to indicate it is a mail ID and not a status code (Implying success for status)
				foreach($query->all() as $row) if($signature == $row['signature']) return 0 - $row['id']; #Match on the header hash

				return 1; #If mail cannot be aquired in the database, claim failure even if the mail is supposed to be on the mail server
			}

			return 0;
		}

		public static function show($id, System_1_0_0_User $user = null) #Get the body of a message
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT folder.id, recent, preference FROM {$database->prefix}mail as mail, {$database->prefix}folder as folder, {$database->prefix}account as account WHERE mail.id = :id AND mail.user = :user AND mail.folder = folder.id AND folder.user = :user AND folder.account = account.id AND account.user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id)); #Get the display preference

			if(!$query->success) return false;
			$row = $query->row();

			list($preference, $folder, $recent) = array($row['preference'], $row['id'], $row['recent']);
			if(!in_array($preference, array('plain', 'html'))) $preference = 'plain'; #Fallback to plain text

			$query = $database->prepare("SELECT seen, plain, html, subject, encode FROM {$database->prefix}mail WHERE id = :id AND user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id)); #Get the mail body

			if(!$query->success) return false;
			$row = $query->row();

			if(!$row['seen']) #Mark as seen if not read (Not minding errors)
			{
				self::flag(array($id), 'Seen', true, $user);

				$query = $database->prepare("UPDATE {$database->prefix}folder SET recent = :recent WHERE id = :id AND user = :user");
				$query->run(array(':recent' => $recent - 1, ':id' => $folder, ':user' => $user->id)); #Update the amount of unread mails

				$query = $database->prepare("SELECT recent FROM {$database->prefix}folder WHERE id = :id AND user = :user");
				$query->run(array(':id' => $folder, ':user' => $user->id)); #Update the amount of unread mails
			}

			if($preference == 'html' && strlen($body = $row['html'])) #If HTML version is available
			{
				header("Content-Type: text/html; charset={$row['encode']}");
				if(!$system->compress_header()) $body = $system->compress_decode($body); #If it cannot send compressed, uncompress and send

				header('Content-Length: '.strlen($body));
				return $body;
			}
			else #Send out the plain text version : NOTE - Not making cache files since a long expire header will be sent and cache hit ratio will not be high enough to inflate user database
			{
				header('Content-Type: text/html; charset=utf-8');

				$container = str_replace(array('%top%', '%id%'), array($system->app_conf('system', 'static', 'root'), $system->self['id']), $system->file_read("{$system->self['root']}/resource/mail.html"));
				$body = str_replace(array('%subject%', '%body%'), array(htmlspecialchars($row['subject']), htmlspecialchars($row['plain'])), $container); #Dump the content in a container

				if($system->compress_header()) $body = gzencode($body); #If gzip compression is allowed, send back compressed
				header('Content-Length: '.strlen($body));

				return $body;
			}
		}

		public static function special($list, $type, $account = null, System_1_0_0_User $user = null) #Move mails to special folders
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!is_array($list) || !in_array($type, array('drafts', 'sent', 'trash')) || $account && !$system->is_digit($account)) return $log->param();
			if(!count($list)) return true;

			foreach($list as $id) if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			if(!$system->is_digit($account))
			{
				$query = $database->prepare("SELECT folder.account FROM {$database->prefix}mail as mail, {$database->prefix}folder as folder WHERE mail.id = :id AND mail.user = :user AND mail.folder = folder.id AND folder.user = :user");
				$query->run(array(':id' => $list[0], ':user' => $user->id)); #Target the account of the mail if not specified

				if(!$query->success) return false;
				$account = $query->column();
			}

			$query = $database->prepare("SELECT folder.name FROM {$database->prefix}account as account, {$database->prefix}folder as folder WHERE account.id = :id AND account.user = :user AND account.folder_$type = folder.id AND folder.user = :user");
			$query->run(array(':id' => $account, ':user' => $user->id)); #Pick the special folder on the specified account

			if(!$query->success) return false;

			$special = $query->column();
			if(!$special) $special = ucfirst($type); #If not defined, use a generic name

			$folder = Mail_1_0_0_Folder::create($account, null, $special, $user); #Try to create it in case it is missing
			if(!$system->is_digit($folder)) return false;

			return self::move($list, $folder, false, $user); #Move the mails to the folder
		}

		public static function text($id, System_1_0_0_User $user = null) #Get the plain text version of a mail
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT plain FROM {$database->prefix}mail WHERE id = :id AND user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id)); #Get the mail body

			return $query->success ? $query->column() : false;
		}

		public static function update($folder, System_1_0_0_User $user = null) #Store any new messages to the local database
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($folder)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT account.id, folder_inbox, receive_host, receive_port, receive_type FROM {$database->prefix}folder as folder, {$database->prefix}account as account WHERE folder.id = :id AND folder.user = :user AND folder.account = account.id AND account.user = :user");
			$query->run(array(':id' => $folder, ':user' => $user->id));

			if(!$query->success) return false;
			$row = $query->row();

			$account = $row['id'];
			if(Mail_1_0_0_Account::type($row['receive_type']) == 'pop3' && $folder != $row['folder_inbox']) return true; #Quit if it is POP3 but not INBOX

			$link = Mail_1_0_0_Account::connect($account, $folder, $user); #Connect to the mail server
			if(!$link) return false;

			$content = imap_check($link['connection']);
			if(!is_object($content)) return Mail_1_0_0_Account::error($link['host']);

			#Mail info insertion query
			$query = array('insert' => $database->prepare("INSERT INTO {$database->prefix}mail (user, folder, uid, header, signature, subject, mid, sent, draft, seen, preview, plain, html, encode) VALUES (:user, :folder, :uid, :header, :signature, :subject, :mid, :sent, :draft, :seen, :preview, :plain, :html, :encode)"));

			foreach(array('from', 'to', 'cc', 'bcc') as $section) #Query to insert mail addresses
				$query[$section] = $database->prepare("INSERT INTO {$database->prefix}$section (mail, name, address) VALUES (:mail, :name, :address)");

			#Attachment info query
			$query['attachment'] = $database->prepare("INSERT INTO {$database->prefix}attachment (mail, cid, name, size, type, position) VALUES (:mail, :cid, :name, :size,:type, :position)");

			$current = array(); #List of currently existing UID or signatures
			$part = $link['info']['supported'] ? 'uid' : 'signature'; #The mail identity to match to find existing mails

			if($link['type'] == 'imap') #For IMAP
			{
				if($link['info']['supported'])
				{
					$unique = imap_search($link['connection'], 'ALL', SE_UID); #Get all the list from the mail server
					array_unshift($unique, null); #Make sure the sequence number starts from 1
				}
				else
				{
					foreach(self::$_flag as $column => $mark) #Prepare for later manipulation when no UID is available
						if($column != 'deleted') $query[$column] = $database->prepare("UPDATE {$database->prefix}mail SET $column = :$column WHERE user = :user AND folder = :folder AND signature = :signature");
				}

				$query['exist'] = $database->prepare("SELECT $part FROM {$database->prefix}mail WHERE user = :user AND folder = :folder");
				$query['exist']->run(array(':user' => $user->id, ':folder' => $folder)); #Get list of existing mails' identifiers

				if(!$query['exist']->success) return false;
				foreach($query['exist']->all() as $row) if(strlen($row[$part])) $current[$row[$part]] = true;
			}
			elseif($link['type'] == 'pop3') #NOTE : Get the UID with an alternative method, since 'imap_search' cannot get UID on POP3
			{
				#Remember downloaded mails for POP3
				$query['loaded'] = $database->prepare("REPLACE INTO {$database->prefix}loaded (user, account, uid, signature) VALUES (:user, :account, :uid, :signature)");

				if($link['info']['supported'])
				{
					$unique = array(); #Store the unique ID for each message
					$connection = Mail_1_0_0_Account::_special($link['info']);

					$pop3 = self::_pop3($system, $connection['receiver']['host'], $connection['receiver']['port'], $connection['receiver']['secure'], $connection['receiver']['user'], $connection['receiver']['pass'], $user);
					if(!$pop3) return false;

					if(!is_array($listing = $pop3->getListing())) return false; #Get all listing
					foreach($listing as $list) $unique[$list['msg_id']] = $list['uidl'];
				}

				$query['exist'] = $database->prepare("SELECT $part FROM {$database->prefix}loaded WHERE user = :user AND account = :account");
				$query['exist']->run(array(':user' => $user->id, ':account' => $account)); #Get list of mail identifiers

				if(!$query['exist']->success) return false;
				foreach($query['exist']->all() as $row) $current[$row[$part]] = true;
			}

			$exist = array(); #List of header md5 sum of mails existing in the mail box
			$field = explode(' ', 'date message_id subject to from cc bcc unseen draft'); #List of fields to get

			for($sequence = 1; $sequence <= $content->Nmsgs; $sequence++) #For all of the messages found in the mail box
			{
				if($link['info']['supported'] && $current[$unique[$sequence]]) #If the given UID is already stored in the database
				{
					unset($current[$unique[$sequence]]); #Filter out existing mails to find the list of non existing mails on the server
					continue; #If already stored, move on
				}

				$attributes = array(':user' => $user->id, ':folder' => $folder, ':uid' => isset($unique[$sequence]) ? $unique[$sequence] : null); #Mail information parameters
				foreach(explode(' ', 'encode mid plain html preview sent subject header') as $empty) $attributes[":$empty"] = null; #NOTE : PHP bug? (Without setting them to null here, random values get inserted)

				$attributes[':header'] = imap_fetchheader($link['connection'], $sequence); #Get the mail header
				if(!$attributes[':header']) return Mail_1_0_0_Account::error($link['host']);

				$attributes[':signature'] = md5($attributes[':header']); #Make a unique signature of the message
				$attributes[':header'] = gzencode($attributes[':header']); #Compress the header for storing

				$addresses = array(); #List of 'from', 'to', 'cc' and 'bcc' addresses

				$header = imap_headerinfo($link['connection'], $sequence);
				if(!is_object($header)) return Mail_1_0_0_Account::error($link['host']);

				foreach($header as $key => $value) #Get mail information
				{
					$key = strtolower($key);

					if($key == 'deleted' && $value == 'D') continue 2; #Ignore deleted messages
					if(!in_array($key, $field)) continue; #Ignore other parameters

					if(!is_array($value))
					{
						switch($key) #Set the column name to store the data
						{
							case 'date' : $store = ':sent'; break;

							case 'unseen' : $store = ':seen'; break;

							case 'message_id' : $store = ':mid'; break;

							default : $store = ":$key"; break;
						}

						$attributes[$store] = self::decode(trim($value));

						switch($key)
						{
							case 'date' : $attributes[$store] = $system->date_datetime(strtotime($attributes[$store])); break; #Format the time

							case 'subject' : if(!$system->is_text($attributes[$store])) $attributes[$store] = ''; break; #Avoid null values

							case 'message_id' : $attributes[$store] = preg_replace('/^<(.+)>$/', '\1', $attributes[$store]); break; #Strip the brackets

							case 'draft' : $attributes[$store] = $value == 'X' ? 1 : 0; break; #Draft entries

							case 'unseen' : $attributes[$store] = $link['type'] == 'pop3' || $value == 'U' ? 0 : 1; break; #Unread entries (Make it unread for all mails under POP3)
						}
					}
					else #Array parameters are list of addresses
					{
						foreach($value as $index => $inner) #Store the mail addresses
						{
							if(!$system->is_digit($index)) continue;
							$parts = array();

							foreach($inner as $name => $text) $parts[$name] = self::decode($text);
							$addresses[$key][] = array(':name' => $parts['personal'] ? $parts['personal'] : null, ':address' => "{$parts['mailbox']}@{$parts['host']}");
						}
					}
				}

				if(!$link['info']['supported']) #If no UID support
				{
					$exist[] = $attributes[':signature']; #Keep remembering the header signatures

					if($current[$attributes[':signature']]) #Check mail existance by header signature
					{
						if($link['type'] == 'imap')
						{
							foreach(self::$_flag as $column => $mark) if($query[$column]) #Flag the states
								$query[$column]->run(array(":$column" => $attributes[':'.strtolower($mark)] ? 0 : 1, ':user' => $user->id, ':folder' => $folder, ':signature' => $attributes[':signature']));
						}

						continue; #Check for the next message (TODO - This avoids having duplicate messages in the mailbox, even possibly with different body)
					}
				}

				$structure = imap_fetchstructure($link['connection'], $sequence); #Get the mail's structure
				if(!is_object($structure)) return Mail_1_0_0_Account::error($link['host']);

				$subtype = strtolower($structure->subtype); #Content's type
				$attachment = $body = $data = array(); #Body data

				if($structure->type == 1) #If it is a multi part message
				{
					self::$_structure = array();
					self::_dig($structure->parts); #Dig the message and get the mime structure

					foreach(self::$_structure as $info) #Look through all of mime parts
					{
						$type = strtolower($info['structure']->subtype);

						if(($type == 'plain' || $type == 'html') && !$body[$type]) #For message body
						{
							if($info['size'] >= self::$_max * 1000) continue; #Ignore huge body

							$body[$type] = imap_fetchbody($link['connection'], $sequence, $info['position'], FT_PEEK); #Get mail body
							if($body[$type] === false) return Mail_1_0_0_Account::error($link['host']);

							$data[$type] = $info['structure']; #Keep the structure
							continue;
						}
						elseif($system->is_digit($info['size']) && ($info['name'] || $info['structure']->id))
						{
							if(!strlen($info['name'])) $info['name'] = ''; #Give an empty name if only Content-ID is given
							$attachment[] = $info; #Keep them as attachments
						}
					}
				}
				elseif($structure->type == 0 && ($subtype == 'plain' || $subtype == 'html') && $structure->bytes < self::$_max * 1000) #If it's a single part message
				{
					$data[$subtype] = $structure; #Specify its structure

					$body[$subtype] = imap_fetchbody($link['connection'], $sequence, 1, FT_PEEK); #Get the body on a single part message
					if($body[$subtype] === false) return Mail_1_0_0_Account::error($link['host']);
				}

				foreach(array('plain', 'html') as $type) #NOTE : Need to check 'plain' first to get 'preview'
				{
					if(!strlen($body[$type]) || !is_object($data[$type])) continue; #If body is not available, do not convert

					switch($data[$type]->encoding) #Decode encoded string
					{
						case 3 : $body[$type] = base64_decode($body[$type]); break;

						case 4 : $body[$type] = quoted_printable_decode($body[$type]); break;
					}

					if($encoding = mb_detect_encoding($body[$type])) #Detect manually first to avoid mails with false or no encoding specified
					{
						if($type == 'plain') $body[$type] = mb_convert_encoding($body[$type], 'utf-8', $encoding); #Convert to UTF8 if it isn't
						else #Keep the encoding as is for HTML version
						{
							#List of detectable character sets : http://www.php.net/manual/en/function.mb-detect-order.php
							switch($encoding) #Determine the character set (TODO : Are '-win' variants valid to be treated same?)
							{
								case 'ASCII' : $attributes[':encode'] = 'us-ascii'; break;

								case 'JIS' : $attributes[':encode'] = 'iso-2022-jp'; break;

								case 'SJIS' : case 'SJIS-win' : $attributes[':encode'] = 'shift_jis'; break;

								case 'EUC-JP' : case 'eucJP-win' : $attributes[':encode'] = 'euc-jp'; break;

								default : $attributes[':encode'] = strtolower($encoding); break;
							}
						}
					}
					else #If it cannot detect, use the encoding specified in the mail if any
					{
						foreach($data[$type]->parameters as $param) #Check for character set
						{
							if(strtolower($param->attribute) != 'charset') continue;
							$encoding = strtolower($param->value);

						  	if($type == 'plain') { if($encoding != 'us-ascii' && $encoding != 'utf-8') $body[$type] = mb_convert_encoding($body[$type], 'utf-8', $encoding); } #Convert to UTF8 if it isn't
							else $attributes[':encode'] = $encoding; #Keep the encoding as is for HTML version
						}
					}

					if($type == 'plain')
					{
						$encoding = mb_detect_encoding($body[$type]); #Detect again to make sure it is in the right character set
						if($encoding != 'ASCII' && $encoding != 'UTF-8') $body[$type] = '(?)'; #If it cannot be converted, empty it

						$attributes[':preview'] = $body[$type];
					}
					elseif(!preg_match('/\S/', $attributes[':preview'])) #If no plain text is available
					{
						if($attributes[':encode']) #If encoding could be detected for HTML version (Already attempted to detect manually above)
						{
							#Create a plain text version from HTML version by taking out the HTML tags and extra spaces off
							if($attributes[':encode'] == 'us-ascii' || $attributes[':encode'] == 'utf-8') $attributes[':plain'] = $body['html'];
							else $attributes[':plain'] = mb_convert_encoding($body['html'], 'utf-8', $attributes[':encode']);

							$attributes[':preview'] = $attributes[':plain'] = preg_replace('/(\s){2,}/', '\1', strip_tags($attributes[':plain']));

							foreach(array(':plain', ':preview') as $section)
							{
								$encoding = mb_detect_encoding($attributes[$section]); #Detect again to make sure
								if($encoding != 'ASCII' && $encoding != 'UTF-8') $attributes[$section] = '(?)'; #If not possible, give up
							}
						}
						else $attributes[':preview'] = $attributes[':plain'] = '(?)'; #If no encoding was detected, save empty preview to avoid corrupting output XML
					}
				}

				if(!$attributes[':plain']) $attributes[':plain'] = $body['plain']; #Keep it uncompressed for search purpose

				#Compress the HTML version - NOTE : In order to open a new window on links under iframe, this is the only way that works across browsers
				$attributes[':html'] = $body['html'] ? gzencode('<head><base target="_blank" /></head>'.$body['html']) : null;

				$attributes[':preview'] = substr($attributes[':preview'], 0, self::$_preview[0]); #Limit the size of the message body TODO - This does not corrupt multi byte characters and the outputting XML?
				$attributes[':preview'] = preg_replace('/^((.*\n){1,'.self::$_preview[1].'})(.|\n)+$/', '\1', preg_replace(array("/\r/", "/\n{2,}/"), array('', "\n"), $attributes[':preview'])); #Limit the lines of the message body

				if(!$database->begin()) return false; #NOTE : Only do transaction per mail, as network failure during fetching mails will not rollback for the mails already saved

				$query['insert']->run($attributes); #Insert the mail in the database
				if(!$query['insert']->success) return $database->rollback() && false;

				$id = $database->id(); #Generated ID for the mail in the database
				if($id === false) return $database->rollback() && false;

				if($link['type'] == 'pop3') #For POP3
				{
					$query['loaded']->run(array(':user' => $user->id, ':account' => $account, ':uid' => $attributes[':uid'], ':signature' => $attributes[':signature'])); #Remember the downloaded mail
					if(!$query['loaded']->success) return $database->rollback() && false;
				}

				foreach(array('from', 'to', 'cc', 'bcc') as $section) #Insert the addresses
				{
					if(!is_array($addresses[$section])) continue;

					foreach($addresses[$section] as $values)
					{
						$values[':mail'] = $id;
						$query[$section]->run($values);

						if(!$query[$section]->success) return $database->rollback() && false;
					}
				}

				foreach($attachment as $file) $query['attachment']->run(array(':mail' => $id, ':cid' => preg_replace('/^<(.+)>$/', '\1', $file['structure']->id), ':name' => $file['name'], ':position' => $file['position'], ':size' => $file['size'], ':type' => $file['type']));
				if(!$database->commit()) return $database->rollback() && false;
			}

			if($link['type'] == 'imap') #On IMAP, do a batch update of mail flags and deletion
			{
				if($link['info']['supported']) #If UID is available
				{
					$state = $set = array();

					foreach(self::$_flag as $column => $mark)
					{
						$list = imap_search($link['connection'], strtoupper($mark), SE_UID); #Get the flagged mails
						if(is_array($list)) foreach($list as $id) $state[$column][$id] = true; #Create lookup hash
					}

					$query['flag'] = $database->prepare("SELECT id, uid, marked, seen, replied FROM {$database->prefix}mail WHERE user = :user AND folder = :folder");
					$query['flag']->run(array(':user' => $user->id, ':folder' => $folder)); #Select all messages in the folder

					if(!$query['flag']->success) return false;
					$a=$query['flag']->all();

					foreach($a as $row)
					{
						foreach(self::$_flag as $column => $mark) #Prepare the difference for update
						{
							if($row[$column]) { if(!$state[$column][$row['uid']]) $set[0][$column][] = $row['id']; } #For removing flag
							elseif($state[$column][$row['uid']]) $set[1][$column][] = $row['id']; #For applying flag
						}
					}

					foreach(self::$_flag as $column => $mark)
					{
						if($column == 'deleted') continue; #Leave messages flagged as deleted by other clients

						for($i = 0; $i <= 1; $i++) #For mails both flags set and unset 
						{
							for($j = 0; $j < count($set[$i][$column]); $j += self::$_limit) #Split by maximum possible place holder number
							{
								$target = array();
								$param = array(":$column" => $i, ':user' => $user->id, ':folder' => $folder);

								foreach(array_slice($set[$i][$column], $j, self::$_limit) as $index => $id)
								{
									$value = $target[] = ":i{$index}d";
									$param[$value] = $id;
								}

								if(!count($target)) continue;
								$target = implode(', ', $target);

								$update = $database->prepare("UPDATE {$database->prefix}mail SET $column = :$column WHERE user = :user AND folder = :folder AND id IN ($target)");
								$update->run($param); #Mark the mails

								if(!$update->success) return false;
							}
						}
					}

					foreach($current as $key => $value) if(strlen($key)) $exist[] = $key; #List of UID not existing on the server anymore
				}
				elseif(!count($exist)) #If no mail is on the server for this folder
				{
					$query = $database->prepare("SELECT id FROM {$database->prefix}mail WHERE user = :user AND folder = :folder");
					$query->run(array(':user' => $user->id, ':folder' => $folder)); #Pick all mails in the folder

					if(!$query->success) return false;
					$target = array();

					foreach($query->all() as $row) $target[] = $row['id'];
					self::remove($target, true, $user); #Remove all the mails in this folder
				}

				if(count($exist)) #If mails should be deleted
				{
					for($i = 0; $i < count($exist); $i += self::$_limit) #Split by maximum possible place holder number
					{
						$target = array();
						$param = array(':user' => $user->id, ':folder' => $folder);

						foreach(array_slice($exist, $i, self::$_limit) as $index => $id)
						{
							$value = $target[] = ":i{$index}d";
							$param[$value] = $id;
						}

						if(!count($target)) continue;
						$target = implode(', ', $target); #Concatenate the identifiers

						#Match on UID for deletion that was not found on the mail server. Otherwise, go through the header hashes for deletion
						$sql = $link['info']['supported'] ? "uid IN ($target)" : "signature NOT IN ($target)";

						$query = $database->prepare("SELECT id FROM {$database->prefix}mail WHERE user = :user AND folder = :folder AND $sql");
						$query->run($param); #Pick non existing mails

						if(!$query->success) return false;
						$target = array();

						foreach($query->all() as $row) $target[] = $row['id'];
						self::remove($target, true, $user); #Remove the mails
					}
				}
			}
			else #For POP3, remove list of downloaded mails those do not exist on the mail server anymore to avoid increasing the data indefinitely
			{
				if($link['info']['supported'])
				{
					$part = 'uid';
					$active = $unique;
				}
				else
				{
					$part = 'signature';
					$active = $exist;
				}

				if(!count($active)) #If no mail is on the server
				{
					$query = $database->prepare("DELETE FROM {$database->prefix}loaded WHERE user = :user AND account = :account");
					$query->run(array(':user' => $user->id, ':account' => $account)); #Delete unused list of downloaded mails (NOTE : Ignoring error here)
				}
				else
				{
					for($i = 0; $i < count($active); $i += self::$_limit) #Split by maximum possible place holder number
					{
						$target = array();
						$param = array(':user' => $user->id, ':account' => $account);

						foreach(array_slice($active, $i, self::$_limit) as $index => $id)
						{
							$value = $target[] = ":i{$index}d";
							$param[$value] = $id;
						}

						if(!count($target)) continue;
						$target = implode(', ', $target);

						$query = $database->prepare("DELETE FROM {$database->prefix}loaded WHERE user = :user AND account = :account AND $part NOT IN ($target)");
						$query->run($param); #Delete unused list of downloaded mails (NOTE : Ignoring error here)
					}
				}
			}

			$query = $database->prepare("UPDATE {$database->prefix}folder SET updated = :updated WHERE id = :id AND user = :user");
			$query->run(array(':updated' => gmdate('Y-m-d H:i:s'), ':id' => $folder, ':user' => $user->id));

			return true; #Report success ignoring the last update query's result
		}
	}
?>

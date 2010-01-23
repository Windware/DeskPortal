<?php
	class Mail_1_0_0_Item #Supports both POP3/IMAP and servers without UIDL or UID support
	{ #TODO : Include PEAR packages as local files - Net_POP3, Mail, Mail_mime
		protected static $_attachment = 50; #Maximum megabytes allowed to be sent as an entire mail

		protected static $_flag = array('deleted' => 'Deleted', 'read' => 'Seen', 'marked' => 'Flagged', 'replied' => 'Answered', 'draft' => 'Draft'); #Table column names and corresponding IMAP flags

		protected static $_limit = 500; #Max number of place holders possible in database queries

		protected static $_max = 200; #Max kilobytes allowed to be transferred from mail servers as the message body. No body will be retrieved if it is bigger.

		protected static $_page = 50; #Default number of mails to display per page

		#Amount of max (bytes, lines) of a message body preview to be sent with the list of mails
		#Any message bodies that are larger will be truncated to the size and lines for previews
		protected static $_preview = array(500, 5);

		protected static $_sent = 'Sent'; #Generic folder name for folders containing sent mails

		protected static $_type = array('text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'); #Mime categories

		private static $_pop3 = array(); #POP3 mail listing cache

		private static $_structure; #Mime structure for each mails

		protected static function _dig($parts, $position = '') #Dig the multi part message
		{
			if(!is_array($parts)) return false;

			if($position) $position = "$position.";
			foreach($parts as $section) self::_dig($section->parts, $position.'1'); #If inner parts exist, check them

			foreach($parts as $index => $section) #Pick the position in the structure
			{
				$name = null; #Attachment file names - NOTE : Decoding rule referred to RFC2231

				if(is_array($section->dparameters)) #If attachment parameters are present
				{
					$file = array();

					foreach($section->dparameters as $param) #Concatenate multi line file names
						if(preg_match('/^filename(\*(\d+)(\*?))?$/', $param->attribute, $matches)) $file[$matches[2]] = $param->value;

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

		public static function attachment($id, $mode = 'attachment') #Get and send the attachment back
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($id) || $mode != 'attachment' && $mode != 'inline') return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT at.name, at.position, at.size, mail.id as mail, mail.folder FROM {$database->prefix}attachment as at, {$database->prefix}mail as mail WHERE at.id = :id AND at.mail = mail.id AND mail.user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id));

			if(!$query->success) return false;
			$attachment = $query->row();

			$query = $database->prepare("SELECT account.id FROM {$database->prefix}folder as folder, {$database->prefix}account as account WHERE folder.id = :id AND folder.user = :user AND folder.account = account.id");
			$query->run(array(':id' => $attachment['folder'], ':user' => $user->id));

			if(!$query->success) return false;

			list($link, $sequence) = self::find(array($attachment['mail']));
			if(!$link || !is_array($sequence)) return false;

			$structure = imap_bodystruct($link['connection'], $sequence[0], $attachment['position']); #Get the attachment information
			$body = imap_fetchbody($link['connection'], $sequence[0], $attachment['position']);#Get the attachment data

			switch($structure->encoding) #Decode encoded string
			{
				case 3 : $body = base64_decode($body); break;

				case 4 : $body = quoted_printable_decode($body); break;
			}

			#Foe IE, URL encode the file name to avoid file name getting corrupted provided as UTF-8
			$name = strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE') ? rawurlencode($attachment['name']) : str_replace('"', '\\"', $attachment['name']);

			header('Content-Type: '.self::$_type[$structure->type].'/'.strtolower($structure->subtype));
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

			$target = implode(', ', $target);

			$query = $database->prepare("SELECT id, folder, uid, sequence, signature FROM {$database->prefix}mail WHERE id IN ($target) AND user = :user");
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
			$link = Mail_1_0_0_Account::connect($query->column(), $folder, $user); #Connect to the server

			$numbers = array(); #Returning sequence numbers

			if(strlen($collection[0]['uid'])) #If UID is available
			{
				if($link['type'] == 'imap')
				{
					$list = imap_search($link['connection'], 'ALL', SE_UID);
					if(!is_array($list)) return Mail_1_0_0_Account::error($link['host']);

					foreach($list as $index => $id) $uid[$id] = $index + 1; #Get unique ID list
				}
				elseif($link['type'] == 'pop3') #List the unique ID and find the message number for POP3
				{
					if($system->file_load('Auth/SASL.php') && $system->file_load('Net/POP3.php'))
					{
						if(!isset(self::$_pop3[$user->id][$account])) #Connect if never connected before
						{
							$secure = $link['info']['receive_secure'] ? 'ssl://' : ''; #Specify SSL connection if configured so
							$pop3 =& new Net_POP3();

							$connect = $pop3->connect($secure.$link['info']['receive_host'], $link['info']['receive_port']); #Connect
							if($connect) $login = $pop3->login($link['info']['receive_user'], $link['info']['receive_pass']); #Login

							self::$_pop3[$user->id][$account] = $login ? $pop3->getListing() : null; #Get all listing and cache it
						}

						if(is_array(self::$_pop3[$user->id][$account])) foreach(self::$_pop3[$user->id][$account] as $list) $uid[$list['uidl']] = $list['msg_id'];
					}
				}

				foreach($collection as $identity) if($system->is_digit($uid[$identity['uid']])) $numbers[] = $uid[$identity['uid']];
			}
			else #When UID/UIDL is not available
			{
				$content = imap_check($link['connection']);
				if(!is_object($content)) return Mail_1_0_0_Account::error($link['host']);

				$target = null;

				foreach($collection as $identity) #For all of the specified messages
				{
					if($identity['sequence'] && $identity['sequence'] <= $content->Nmsgs) #Reuse the sequence number
					{
						$signature = imap_fetchheader($link['connection'], $identity['sequence']); #Try to see if the sequence number still matches
						if(!$signature) return Mail_1_0_0_Account::error($link['host']);

						$signature = md5($signature);
						if($signature == $identity['signature']) $target = $identity['sequence']; #Use it if it matches
					}

					#NOTE : This is a very slow operation only used when UID/UIDL is not supported by the IMAP/POP3 server
					if(!$target) #If not found, go through entire messages and find the matching mail
					{
						for($sequence = 1; $sequence <= $content->Nmsgs; $sequence++)
						{
							$signature = imap_fetchheader($link['connection'], $sequence); #Signature of a message
							if(!$signature) return Mail_1_0_0_Account::error($link['host']);

							$signature = md5($signature);
							if($signature != $identity['signature']) continue; #Find the message

							$target = $sequence;
							break;
						}

						if(!$target) return $log->dev(LOG_WARNING, "Cannot find the mail data on the server for mail ID '$id'", 'Mail may have been deleted from another client. Try to synchronize the mail box.');
					}

					$numbers[] = $target; #Stack the sequence numbers
				}
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

			if($flag != 'Deleted') #Nothing to flag for 'Deleted' on the database
			{
				foreach(self::$_flag as $column => $mark) if($mark == $flag) $set = $column; #Find the column name for the flag

				for($i = 0; $i < count($list); $i += self::$_limit) #Split into flagging limit count ID at once to avoid place holder limitation
				{
					$target = array();
					$param = array(':mode' => $mode ? 1 : 0, ':user' => $user->id);

					foreach(array_slice($list, $i, self::$_limit) as $index => $id)
					{
						if(!$system->is_digit($index)) continue;

						$value = $target[] = ":i{$index}d";
						$param[$value] = $id;
					}

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
				$filter .= ' AND read = :read';
				$param[':read'] = 0;
			}

			switch($order) #Join the extra table for address sorting
			{
				case 'from' : case 'to' : case 'cc' :
					$foreign = " LEFT JOIN {$database->prefix}$order as _$order ON id = _$order.mail";
					$order = 'address'; #Specify the column name for the address to sort
				break;
			}

			if(is_string($search) && strlen($search) > 1)
			{
				foreach(array('from', 'to', 'cc') as $target)
				{
					if($order != $target) $foreign .= " LEFT JOIN {$database->prefix}$target as _$target ON id = _$target.mail";
					$limiter .= " OR _$target.name LIKE :search $database->escape OR _$target.address LIKE :search $database->escape";
				}

				$filter .= " AND (subject LIKE :search $database->escape$limiter)";
				$param[':search'] = '%'.$system->database_escape($search).'%';
			}

			$order = "LOWER($order)"; #NOTE : Using 'LOWER' for case insensitive sorting to be compatible across database, possibly FIXME for performance
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

			$xml = $system->xml_node('page', array('total' => floor($total / ($amount + 1)) + 1)); #Get the total count

			$query['all'] = $database->prepare("SELECT id, subject, sent, received, preview, marked, read, replied FROM {$database->prefix}mail$foreign WHERE user = :user AND folder = :folder$filter ORDER BY $order$reverse LIMIT $start,$amount");
			$query['all']->run($param);

			if(!$query['all']->success) return false;

			$all = $query['all']->all();
			$target = $param = array();

			foreach($all as $index => $row)
			{
				$value = $target[] = ":i{$index}d";
				$param[$value] = $row['id'];
			}

			$target = implode(', ', $target);
			$mail = array();

			foreach(array('from', 'to', 'cc') as $section)
			{
				$query['address'] = $database->prepare("SELECT mail, name, address FROM {$database->prefix}$section WHERE mail IN ($target)");
				$query['address']->run($param);

				if(!$query['address']->success) return false;
				foreach($query['address']->all() as $row) $mail[$row['mail']][$section][] = array('name' => $row['name'], 'address' => $row['address']);
			}

			$query['attachment'] = $database->prepare("SELECT id, mail, cid, name, size, type FROM {$database->prefix}attachment WHERE mail IN ($target)");
			$query['attachment']->run($param);

			if(!$query['attachment']->success) return false;
			foreach($query['attachment']->all() as $row) $mail[$row['mail']]['attachment'][] = array('id' => $row['id'], 'cid' => $row['cid'], 'name' => $row['name'], 'size' => $row['size'], 'type' => $row['type']);

			foreach($all as $row)
			{
				$inner = '';

				foreach(array('from', 'to', 'cc', 'attachment') as $section) #Construct the mail address and attachment lists
					if(is_array($mail[$row['id']][$section])) foreach($mail[$row['id']][$section] as $line) $inner .= $system->xml_node($section, $line);

				$inner .= $system->xml_node('preview', null, $system->xml_data($row['preview']));
				unset($row['preview']);

				$row['account'] = $account;
				$xml .= $system->xml_node('mail', $row, $inner);
			}

			return $xml;
		}

		public static function image($cid) #Get the embedded image data
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_text($cid)) return $log->param();
			if(strlen($cid) > 255) return false;

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT at.id FROM {$database->prefix}attachment as at, {$database->prefix}mail as mail WHERE at.cid = :cid AND at.mail = mail.id AND mail.user = :user");
			$query->run(array(':cid' => $cid, ':user' => $user->id));

			if(!$query->success) return false;
			return self::attachment($query->column(), 'inline');
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

				$supported = $system->is_digit($current['uid']); #If UID is supported or not

				$target = mb_convert_encoding(Mail_1_0_0_Folder::name($folder), 'UTF7-IMAP', 'UTF-8'); #Target folder name
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
					self::remove($list, true); #Remove mails in the source folder
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

						$target = implode(', ', $target);

						$query = $database->prepare("UPDATE {$database->prefix}mail SET folder = :folder WHERE id IN ($target) AND user = :user");
						$query->run($param); #Move to another folder

						if(!$query->success) return false;
					}
				}
				else #On copy
				{
					$column = 'user, uid, sequence, signature, subject, mail, size, sent, received, preview, plain, html, encode, marked, read, replied, draft, color';

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
			$database->begin(); #Make the deletion atomic

			for($i = 0; $i < count($list); $i += self::$_limit) #Split by maximum possible place holder number
			{
				$param = $target = array();

				foreach(array_slice($list, $i, self::$_limit) as $index => $id)
				{
					if(!$system->is_digit($index)) continue;

					$value = $target[] = ":i{$index}d";
					$param[$value] = $id;
				}

				$target = implode(', ', $target);

				foreach(explode(' ', 'from to cc attachment reference') as $section) #Remove related data
				{
					$query = $database->prepare("DELETE FROM {$database->prefix}$section WHERE mail IN ($target)");
					$query->run($param);

					if($query->success) continue;

					$database->rollback();
					return false;
				}

				$query = $database->prepare("DELETE FROM {$database->prefix}reference WHERE reference IN ($target)");
				$query->run($param);

				if(!$query->success)
				{
					$database->rollback();
					return false;
				}

				$param[':user'] = $user->id;

				$query = $database->prepare("DELETE FROM {$database->prefix}mail WHERE id IN ($target) AND user = :user");
				$query->run($param); #Delete the mail entry

				if($query->success) continue;

				$database->rollback();
				return false;
			}

			return $database->commit();
		}

		#NOTE : Error codes are (0 : success, 1 : system error, 2 : size too big, 3 : SMTP send error, 4 : IMAP store error)
		public static function send($account, $subject, $body, $to, $cc = null, $bcc = null, $source = null, $attachment = array(), System_1_0_0_User $user = null) #Send a mail
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account)) return $log->param(1);

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
			$mime = new Mail_mime("\n"); #Create the mail MIME object

			$mime->setTxtBody($body); #Set the message body
			foreach($attachment as $file) $mime->addAttachment(file_get_contents($file['tmp_name']), $file['type'], $file['name'], false); #Add attachment files if given

			$query = $database->prepare("SELECT * FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $account, ':user' => $user->id));

			if(!$query->success) return 1;

			$row = $query->row();
			$encoded = $toward = array(); #List of encoded addresses and raw mail addresses to send to

			foreach(imap_rfc822_parse_adrlist("{$row['name']} <{$row['address']}>", '') as $parsed)
			{
				if($parsed->host == '.SYNTAX-ERROR.') return 3; #Parse the 'from' addresses

				$address = "{$parsed->mailbox}@{$parsed->host}";
				$encoded['from'][] = strlen($parsed->personal) ? mb_encode_mimeheader($parsed->personal)." <$address>" : $address;
			}

			foreach(imap_rfc822_parse_adrlist($to, '') as $parsed) #Parse the 'to' addresses
			{
				if($parsed->host == '.SYNTAX-ERROR.') return 3; #Return as SMTP error for invalid addresses

				$address = $toward[] = "{$parsed->mailbox}@{$parsed->host}";
				$encoded['to'][] = strlen($parsed->personal) ? mb_encode_mimeheader($parsed->personal)." <$address>" : $address;
			}

			$header = array( #Headers to go inside the mail header section
				'From' => implode(', ', $encoded['from']),
				'To' => implode(', ', $encoded['to']),
				'Subject' => mb_encode_mimeheader($subject),
				'Date' => gmdate('r'),
				'Message-ID' => time().'-'.md5(mt_rand()).'@'.preg_replace('/^.+@/', '', $row['address']), #Create a unique message ID
				'X-Mailer' => str_replace(array('%APP%', '%VERSION%'), array($system->self['name'], str_replace('_', '.', $system->self['version'])), $system->app_conf($system->self['name'], $system->self['version'], 'mailer')),
				'Return-Path' => $row['address']
			);

			$mail = array('body' => $mime->get(array('head_charset' => 'utf-8', 'text_charset' => 'utf-8')), 'header' => $mime->headers($header)); #NOTE : Do not call these functions in reverse order (From PEAR manual)

			if($system->is_digit($row['folder_sent']))
			{
				$folder = $row['folder_sent'];
				$name = Mail_1_0_0_Folder::name($folder, $user); #Find the 'sent' folder
			}

			if(!is_string($name) || !strlen($name)) #If not found
			{
				$name = self::$_sent;
				$folder = Mail_1_0_0_Folder::create($name); #Register a generic folder name

				if(!$folder) return 1;

				$query = $database->prepare("UPDATE {$database->prefix}account SET folder_sent = :folder WHERE id = :id user = :user");
				$query->run(array(':folder' => $folder, ':id' => $account, ':user' => $user->id));

				if(!$query->success) return 1;
			}

			$type = Mail_1_0_0_Account::type($row['receive_type']);
			$open = $type == 'pop3' ? '' : $folder;

			$link = Mail_1_0_0_Account::connect($account, $open, $user); #Connect for both POP3/IMAP to validate through 'POP3/IMAP before SMTP'

			$connection = array( #SMTP connection parameters
				'host' => ($row['send_secure'] ? 'tls://' : '').$row['send_host'],
				'port' => $row['send_port'],
				'auth' => strlen($row['send_user']) || strlen($row['send_pass']),
				'username' => $row['send_user'],
				'password' => $row['send_pass'],
				'localhost' => $row['send_host'],
				'timeout' => $system->app_conf('system', 'static', 'net_timeout'),
				'persist' => false
			);

			$smtp =& Mail::factory('smtp', $connection);
			if($smtp->send($toward, $mail['header'], $mail['body']) !== true) return 3; #SMTP failure

			if($type == 'pop3') return 0; #Keep the mail on the mail server for IMAP

			$header = '';
			foreach($mail['header'] as $key => $value) $header .= "$key: $value\r\n";

			if(imap_append($link['connection'], '{'.$link['host']."}$name", "$header\r\n{$mail['body']}")) return 0; #Save on the mail server

			Mail_1_0_0_Account::error($link['host']);
			return 4; #IMAP failure
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

			$query = $database->prepare("SELECT read, plain, html, subject, encode FROM {$database->prefix}mail WHERE id = :id AND user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id)); #Get the mail body

			if(!$query->success) return false;
			$row = $query->row();

			if(!$row['read']) self::flag(array($id), 'Seen', true, $user); #Mark as seen if not read

			if($body = $row['html']) #If HTML version is available
			{
				header("Content-Type: text/html; charset={$row['encode']}");
				if(!$system->compress_header()) $body = $system->compress_decode($body); #If it cannot send compressed, uncompress and send

				header('Content-Length: '.strlen($body));
				return $body;
			}
			else #Send out the plain text version : NOTE - Not making cache since a long expire header will be sent and cache hit ratio will not be high enough to inflate user database
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

			if(!$query->success) return false;

			$body = $query->column();
			if($system->compress_header()) $body = gzencode($body); #If gzip compression is allowed, send back compressed

			header('Content-Type: text/html; charset=utf-8');
			header('Content-Length: '.strlen($body));

			return $body;
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

			$query = $database->prepare("SELECT account.id, folder_inbox, receive_type FROM {$database->prefix}folder as folder, {$database->prefix}account as account WHERE folder.id = :id AND folder.user = :user AND folder.account = account.id AND account.user = :user");
			$query->run(array(':id' => $folder, ':user' => $user->id));

			if(!$query->success) return false;
			$row = $query->row();

			$account = $row['id'];
			if(Mail_1_0_0_Account::type($row['receive_type']) == 'pop3' && $folder != $row['folder_inbox']) return true; #Quit if it is POP3 but not INBOX

			$link = Mail_1_0_0_Account::connect($account, $folder, $user); #Connect to the mail server
			if(!$link) return false;

			$content = imap_check($link['connection']);
			if(!is_object($content)) return Mail_1_0_0_Account::error($link['host']);

			$query = $database->prepare("SELECT uid FROM {$database->prefix}folder as folder, {$database->prefix}mail as mail WHERE folder.user = :user AND folder.account = :account AND folder.id = mail.folder LIMIT 1");
			$query->run(array(':user' => $user->id, ':account' => $account)); #See if UID is supported

			$supported = !!strlen($query->column()); #If UID can be used to make queries shorter to the mail server : TODO - If the mail server changes its UID capability during use, it will break mail lookup

			$query = $database->prepare("SELECT uid FROM {$database->prefix}mail WHERE user = :user AND folder = :folder");
			$query->run(array(':user' => $user->id, ':folder' => $folder)); #Get list of UID

			if(!$query->success) return false;

			$current = array(); #List of currently existing UID
			foreach($query->all() as $row) if(strlen($row['uid'])) $current[$row['uid']] = true;

			#Prepare to check for mail's existence
			$query = array('check' => $database->prepare("SELECT id FROM {$database->prefix}mail WHERE user = :user AND folder = :folder AND signature = :signature"));

			foreach(array('from', 'to', 'cc') as $section) #Query to insert mail addresses
				$query[$section] = $database->prepare("INSERT INTO {$database->prefix}$section (mail, name, address) VALUES (:mail, :name, :address)");

			#Mail info insertion query
			$query['insert'] = $database->prepare("INSERT INTO {$database->prefix}mail (user, folder, uid, sequence, header, signature, subject, sent, read, preview, plain, html, encode) VALUES (:user, :folder, :uid, :sequence, :header, :signature, :subject, :sent, :read, :preview, :plain, :html, :encode)");

			#Attachment info query
			$query['attachment'] = $database->prepare("INSERT INTO {$database->prefix}attachment (mail, cid, name, size, type, position) VALUES (:mail, :cid, :name, :size, :type, :position)");

			if($link['type'] == 'imap') #For IMAP
			{
				#TODO - What does it return when UID is not supported? It may confuse the script that the server supports UID if a list is returned
				$unique = imap_search($link['connection'], 'ALL', SE_UID); #Get the UID list from the mail server

				if(!is_array($unique) || !count($unique)) foreach(self::$_flag as $column => $mark) if($column != 'deleted') #Prepare for later manipulation when no UID is available
					$query[$column] = $database->prepare("UPDATE {$database->prefix}mail SET $column = :$column WHERE user = :user AND folder = :folder AND signature = :signature");

				if(is_array($unique)) array_unshift($unique, null); #Make sure the sequence number starts from 1
			}
			elseif($link['type'] == 'pop3') #NOTE : Get the UID with an alternative method, since 'imap_search' cannot get UID on POP3
			{
				if($system->file_load('Auth/SASL.php') && $system->file_load('Net/POP3.php'))
				{
					if(!isset(self::$_pop3[$user->id][$account])) #Connect if never connected before
					{
						$secure = $link['info']['receive_secure'] ? 'ssl://' : ''; #Specify SSL connection if configured so
						$pop3 =& new Net_POP3();

						$connect = $pop3->connect($secure.$link['info']['receive_host'], $link['info']['receive_port']); #Connect
						if($connect) $login = $pop3->login($link['info']['receive_user'], $link['info']['receive_pass']); #Login

						self::$_pop3[$user->id][$account] = $login ? $pop3->getListing() : null; #Get all listing and cache it
					}

					$unique = array(); #Store the unique ID for each message
					if(is_array(self::$_pop3[$user->id][$account])) foreach(self::$_pop3[$user->id][$account] as $list) $unique[$list['msg_id']] = $list['uidl'];
				}
			}

			$exist = array(); #List of header md5 sum of mails existing in the mail box
			$field = explode(' ', 'date subject to from unseen'); #List of fields to get

			for($sequence = 1; $sequence <= $content->Nmsgs; $sequence++) #For all of the messages found in the mail box
			{
				if($current[$unique[$sequence]]) #If the given UID is already stored in the database
				{
					unset($current[$unique[$sequence]]); #Filter out existing mails to find the list of non existing mails on the server
					continue; #If already stored, move on
				}

				$attributes = array(':user' => $user->id, ':folder' => $folder, ':sequence' => $sequence, ':uid' => isset($unique[$sequence]) ? $unique[$sequence] : null); #Mail information parameters
				foreach(explode(' ', 'encode plain html preview sent subject header') as $empty) $attributes[":$empty"] = null; #NOTE : PHP bug? (Without setting them to null here, random values get inserted)

				$attributes[':header'] = imap_fetchheader($link['connection'], $sequence); #Get the mail header
				if(!$attributes[':header']) return Mail_1_0_0_Account::error($link['host']);

				$attributes[':signature'] = md5($attributes[':header']); #Make a unique signature of the message
				$attributes[':header'] = gzencode($attributes[':header']); #Compress the header for storing

				$addresses = array(); #List of 'from', 'to' and 'cc' addresses

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

							case 'unseen' : $store = ':read'; break;

							default : $store = ":$key"; break;
						}

						$attributes[$store] = self::decode(trim($value));

						switch($key)
						{
							case 'date' : $attributes[$store] = $system->date_datetime(strtotime($attributes[$store])); break; #Format the time

							case 'subject' : if(!$system->is_text($attributes[$store])) $attributes[$store] = ''; break; #Avoid null values

							case 'unseen' : $attributes[$store] = $value == 'U' ? 0 : 1; break; #Unread entries
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

				if(!$supported) #If no UID support
				{
					$exist[] = $attributes[':signature']; #Keep remembering the header signatures

					$query['check']->run(array(':user' => $user->id, ':folder' => $folder, ':signature' => $attributes[':signature']));
					if(!$query['check']->success) return false; #Quit the entire function if it fails

					if($query['check']->column()) #Check mail existance by header signature
					{
						foreach(self::$_flag as $column => $mark) if($query[$column]) #Flag the states
							$query[$column]->run(array(":$column" => $attributes[':'.strtolower($mark)] ? 0 : 1, ':user' => $user->id, ':folder' => $folder, ':signature' => $attributes[':signature']));

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
						elseif($system->is_digit($info['size']) && $info['name']) $attachment[] = $info; #Keep them as attachments
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
							switch($encoding) #Determine the character set - TODO : Code is language biased and possibly inaccurate for '-win' variants
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
					elseif(!$attributes[':preview']) #If no plain text is available
					{
						if($attributes[':encode']) #If encoding could be detected for HTML version
						{
							$stripped = trim(strip_tags($body[$type])); #Create a plain text version from HTML version by taking out the HTML tags and surrounding spaces off
							if($attributes[':encode'] != 'us-ascii' && $attributes[':encode'] != 'utf-8') $stripped = mb_convert_encoding($stripped, 'utf-8', $attributes[':encode']);

							foreach(array(':plain', ':preview') as $section)
							{
								$attributes[$section] = $stripped; #Set plain text versions

								$encoding = mb_detect_encoding($attributes[$section]); #Detect again to make sure
								if($encoding != 'ASCII' && $encoding != 'UTF-8') $attributes[$section] = '(?)'; #If not possible, give up
							}
						}
						else $attributes[':preview'] = '(?)'; #If no encoding was detected, save empty preview to avoid corrupting output XML
					}
				}

				$attributes[':plain'] = $body['plain']; #Keep it uncompressed for search purpose

				#Compress the HTML version - NOTE : In order to open a new window on links under iframe, this is the only way that works across browsers
				$attributes[':html'] = $body['html'] ? gzencode('<head><base target="_blank" /></head>'.$body['html']) : null;

				$attributes[':preview'] = substr($attributes[':preview'], 0, self::$_preview[0]); #Limit the size of the message body TODO - This does not corrupt multi byte characters and the outputting XML?
				$attributes[':preview'] = preg_replace('/^((.*\n){1,'.self::$_preview[1].'})(.|\n)+$/', '\1', preg_replace(array("/\r/", "/\n{2,}/"), array('', "\n"), $attributes[':preview'])); #Limit the lines of the message body

				$database->begin(); #NOTE : Only do transaction per mail, as network failure during fetching mails will not rollback for the mails already saved

				$query['insert']->run($attributes); #Insert the mail in the database
				if(!$query['insert']->success) return false;

				$id = $database->id(); #Generated ID for the mail in the database

				foreach(array('from', 'to', 'cc') as $section) #Insert the addresses
				{
					if(!is_array($addresses[$section])) continue;

					foreach($addresses[$section] as $values)
					{
						$values[':mail'] = $id;

						$query[$section]->run($values);
						if($query[$section]->success) continue;

						$database->rollback();
						return false;
					}
				}

				foreach($attachment as $file) $query['attachment']->run(array(':mail' => $id, ':cid' => preg_replace('/^<(.+)>$/', '\1', $file['structure']->id), ':name' => $file['name'], ':position' => $file['position'], ':size' => $file['size'], ':type' => $file['type']));
				$database->commit();
			}

			if($supported) #If UID is available
			{
				if($link['type'] == 'imap') #On IMAP, do a batch update of mail flags
				{
					$state = $set = array();

					foreach(self::$_flag as $column => $mark)
					{
						$list = imap_search($link['connection'], strtoupper($mark), SE_UID); #Get the flagged mails
						if(is_array($list)) foreach($list as $id) $state[$column][$id] = true; #Create lookup hash
					}

					$query['flag'] = $database->prepare("SELECT id, uid, marked, read, replied FROM {$database->prefix}mail WHERE user = :user AND folder = :folder");
					$query['flag']->run(array(':user' => $user->id, ':folder' => $folder)); #Select all messages in the folder

					if(!$query['flag']->success) return false;

					foreach($query['flag']->all() as $row)
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

								$target = implode(', ', $target);

								$update = $database->prepare("UPDATE {$database->prefix}mail SET $column = :$column WHERE user = :user AND folder = :folder AND id IN ($target)");
								$update->run($param); #Mark the mails

								if(!$update->success) return false;
							}
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
						$param[$value] = $exist[$index];
					}

					$target = implode(', ', $target); #Concatenate the identifiers

					#Match on UID for deletion that was not found on the mail server. Otherwise, go through the header hashes for deletion
					$sql = $supported ? "uid IN ($target)" : "signature NOT IN ($target)";

					$query = $database->prepare("SELECT id FROM {$database->prefix}mail WHERE user = :user AND folder = :folder AND $sql");
					$query->run($param); #Pick non existing mails

					if(!$query->success) return false;
					$target = array();

					foreach($query->all() as $row) $target[] = $row['id'];
					self::remove($target, true, $user); #Remove the mails
				}
			}

			$query = $database->prepare("UPDATE {$database->prefix}folder SET updated = :updated WHERE id = :id AND user = :user");
			$query->run(array(':updated' => date('Y-m-d H:i:s'), ':id' => $folder, ':user' => $user->id));

			return true; #Report success ignoring the last update query's result
		}
	}
?>

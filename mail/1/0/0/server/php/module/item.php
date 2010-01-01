<?php
	class Mail_1_0_0_Item
	{
		protected static $_max = 300000; #Max bytes allowed to be transferred from mail servers as the message body. No body will be retrieved if it is bigger.

		protected static $_page = 30; #Default number of mails to display per page

		protected static function _dig($parts, $position = '') #Dig the multi part message and find the main body section
		{
			if(!is_array($parts)) return array(null, false);
			if($position) $position = "$position.";

			foreach($parts as $index => $section) #If inner parts exist, check them first
				if($section->parts && $result = self::_dig($section->parts, $position.'1')) return $result;

			#Pick plain text or HTML parts preferred in that order
			foreach($parts as $index => $section) if(strtolower($section->subtype) == 'plain') return array($section, $position.++$index);
			foreach($parts as $index => $section) if(strtolower($section->subtype) == 'html') return array($section, $position.++$index);

			return array(null, false);
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

			$query['count'] = $database->prepare("SELECT count(id) FROM {$database->prefix}mail$foreign WHERE user = :user AND folder = :folder$filter");
			$query['count']->run($param);

			if(!$query['count']->success) return false;

			$total = $query['count']->column();
			if(!$total) $total = 0;

			$xml = $system->xml_node('page', array('total' => floor($total / ($amount + 1)) + 1)); #Get the total count

			$query['all'] = $database->prepare("SELECT id, subject, sent, received, marked, read, replied FROM {$database->prefix}mail$foreign WHERE user = :user AND folder = :folder$filter ORDER BY $order$reverse LIMIT $start,$amount");
			$query['all']->run($param);

			if(!$query['all']->success) return false;
			foreach(array('from', 'to', 'cc') as $section) $query[$section] = $database->prepare("SELECT name, address FROM {$database->prefix}$section WHERE mail = :mail");

			foreach($query['all']->all() as $row) #Construct the mail information
			{
				$addresses = '';

				foreach(array('from', 'to', 'cc') as $section)
				{
					$query[$section]->run(array(':mail' => $row['id'])); #TODO - Run this once with 'IN' clause (Gets run 60 times)
					if(!$query[$section]->success) return false;

					foreach($query[$section]->all() as $line) $addresses .= $system->xml_node($section, $line);
				}

				$xml .= $system->xml_node('mail', $row, $addresses);
			}

			return $xml;
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

			$query = $database->prepare("SELECT folder, uid, sequence, signature FROM {$database->prefix}mail WHERE id = :id AND user = :user");
			$query->run(array(':id' => $id, ':user' => $user->id));

			if(!$query->success) return false;
			$identity = $query->row(); #Get the mail's identifiers

			$query = $database->prepare("SELECT account FROM {$database->prefix}folder WHERE id = :id AND user = :user");
			$query->run(array(':id' => $identity['folder'], ':user' => $user->id));

			if(!$query->success) return false;
			$link = Mail_1_0_0_Account::connect($query->column(), $identity['folder'], $user); #Connect to the server

			$content = imap_check($link['connection']);
			if(!$content) $log->user(LOG_ERR, "Invalid mailbox '$folder' on host '{$link['host']}'", 'Check the mail server or configuration');

			if($identity['uid']) #If the mail has an unique identifier, set the target for that mail (NOTE : This block is an optional procedure to speed up the message look up)
			{
				if($link['info']['receive_type'] == 'imap') $sequence = imap_msgno($link['connection'], $identity['uid']); #Get message number from the ID
				elseif($link['info']['receive_type'] == 'pop3') #List the unique ID and find the message number for POP3
				{
					if((@include_once('Auth/SASL.php')) && (@include_once('Net/POP3.php'))) #NOTE : Requires these PHP PEAR packages - FIXME - Have a local copy of PEAR
					{
						$secure = $link['info']['receive_secure'] ? 'ssl://' : ''; #Specify SSL connection if configured so
						$pop3 =& new Net_POP3();

						if($pop3->connect($secure.$link['info']['receive_host'], $link['info']['receive_port'])) #Connect
						{
							if($pop3->login($link['info']['receive_user'], $link['info']['receive_pass']))
							{
								$all = $pop3->getListing();
								if(is_array($all)) foreach($all as $list) if($list['uidl'] == $identity['uid']) $sequence = $list['msg_id'];
							}

							$pop3->disconnect();
						}
					}
				}

				if($system->is_digit($sequence))
				{
					$signature = md5(imap_fetchheader($link['connection'], $sequence));
					if($signature == $identity['signature']) $target = $sequence;
				}
			}

			if(!$target) #If no unique identifier is given
			{
				if($identity['sequence'])
				{
					$signature = md5(imap_fetchheader($link['connection'], $identity['sequence'])); #Try to see if the sequence number still matches
					if($signature == $identity['signature']) $target = $sequence; #Use it if it matches
				}

				if(!$target) #Otherwise, go through entire messages and find the matching mail
				{
					for($sequence = 1; $sequence <= $content->Nmsgs; $sequence++)
					{
						$signature = md5(imap_fetchheader($link['connection'], $sequence)); #Signature of a message
						if($signature != $identity['signature']) continue; #Find the message

						$target = $sequence;
						break;
					}
				}

				if(!$target) return $log->dev(LOG_WARNING, "Cannot find the mail data on the server for mail ID '$id'", 'Mail may have been deleted from another client');
			}

			$structure = imap_fetchstructure($link['connection'], $target); #Get the mail's structure
			$body = ''; #The message body

			if($structure->type == 1) #If it is a multi part message
			{
				list($data, $main) = self::_dig($structure->parts); #Dig the message and find the body part
				if($data && $data->bytes < self::$_max) $body = imap_fetchbody($link['connection'], $target, $main); #Get the content as body if it's valid
			}
			elseif($structure->type == 0 && strtolower($structure->subtype) == 'plain' && $structure->bytes < self::$_max) #If it's a single part message having plain text
			{
				$main = '1'; #Body ID for single part message

				$body = imap_fetchbody($link['connection'], $target, $main); #Get the body on a single part message
				$data = $structure; #Specify its structure
			}

			if($body && is_object($data)) #Only if any textual data is found
			{
				switch($data->encoding) #Decode encoded string
				{
					case 3 : $body = base64_decode($body); break;

					case 4 : $body = quoted_printable_decode($body); break;
				}

				$encoding = mb_detect_encoding($body); #Detect manually first to avoid mails with false or no encoding specified

				if($encoding) $body = mb_convert_encoding($body, 'utf-8', $encoding); #Convert to UTF8 if it isn't
				else #If it cannot detect, trust the encoding specified in the mail
				{
					foreach($data->parameters as $param) #Check for character set
					{
						if(strtolower($param->attribute) != 'charset' || strtolower($param->value) == 'us-ascii' || strtolower($param->value) == 'utf-8') continue;
						$body = mb_convert_encoding($body, 'utf-8', $param->value); #Convert to UTF8 if it isn't
					}
				}

				$encoding = mb_detect_encoding($body);
				if($encoding != 'ASCII' && $encoding != 'UTF-8') $body = '(?)'; #If it cannot be converted, do not send the content to avoid corrupting XML
			}

			$query = $database->prepare("UPDATE {$database->prefix}mail SET read = :read WHERE id = :id AND user = :user");
			$query->run(array(':read' => 1, ':id' => $id, ':user' => $user->id)); #Update the read status

			return $system->xml_node('body', null, $system->xml_data($body));
		}

		public static function update($folder, System_1_0_0_User $user = null) #Store any new messages in the local database
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($folder)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT account FROM {$database->prefix}folder WHERE id = :id AND user = :user");
			$query->run(array(':id' => $folder, ':user' => $user->id));

			if(!$query->success) return false;
			$account = $query->column();

			$link = Mail_1_0_0_Account::connect($account, $folder, $user); #Connect to the server

			$content = imap_check($link['connection']);
			if(!$content) return $log->user(LOG_ERR, "Invalid mailbox '$folder' on host '{$link['host']}'", 'Check the mail server or configuration');

			$query = array('list' => $database->prepare("SELECT uid FROM {$database->prefix}mail WHERE user = :user AND folder = :folder"));
			$query['list']->run(array(':user' => $user->id, ':folder' => $folder)); #Get list of UID

			if(!$query['list']->success) return false;

			$current = array(); #List of currently existing UID
			foreach($query['list']->all() as $row) if(strlen($row['uid'])) $current[$row['uid']] = true;

			#Prepare to check for mail's existence
			$query['check'] = $database->prepare("SELECT id FROM {$database->prefix}mail WHERE user = :user AND folder = :folder AND signature = :signature");

			foreach(array('from', 'to', 'cc') as $section) #Query to insert mail addresses
				$query[$section] = $database->prepare("INSERT INTO {$database->prefix}$section (mail, name, address) VALUES (:mail, :name, :address)");

			#Mail data insertion query
			$query['insert'] = $database->prepare("INSERT INTO {$database->prefix}mail (user, folder, uid, sequence, signature, subject, sent, read) VALUES (:user, :folder, :uid, :sequence, :signature, :subject, :sent, :read)");

			if($link['info']['receive_type'] == 'imap') #For IMAP
			{
				$unique = imap_search($link['connection'], 'ALL', SE_UID); #Get the UID list from the mail server
				if(is_array($unique)) array_unshift($unique, null); #Make sure the sequence number starts from 1

				$flag = array('read' => 'SEEN', 'marked' => 'FLAGGED', 'replied' => 'ANSWERED'); #Table column names and corresponding IMAP flags

				foreach($flag as $column => $mark)
				{
					if(count($current)) #If UID are available : TODO - If the mail server changes its UID capability during use, it will break mail lookup
					{
						$list = array();

						$target = imap_search($link['connection'], $mark, SE_UID); #Get the flagged mails
						if(!is_array($target)) continue;

						foreach($target as $id) $list[] = $database->handler->quote($id);
						$list = implode(',', $list);

						$query[$column] = $database->prepare("UPDATE {$database->prefix}mail SET $column = :$column WHERE user = :user AND folder = :folder AND uid IN ($list)");
						$query[$column]->run(array(":$column" => 1, ':user' => $user->id, ':folder' => $folder)); #Mark the mails

						$query[$column] = $database->prepare("UPDATE {$database->prefix}mail SET $column = :$column WHERE user = :user AND folder = :folder AND uid NOT IN ($list)");
						$query[$column]->run(array(":$column" => 0, ':user' => $user->id, ':folder' => $folder)); #Remove the mark off mails
					}
					#Prepare for later manipulation when no UID is available
					else $query[$mark] = $database->prepare("UPDATE {$database->prefix}mail SET $mark = :$mark WHERE user = :user AND folder = :folder AND signature = :signature");
				}
			}
			elseif($link['info']['receive_type'] == 'pop3') #NOTE : Get the UID with an alternative method, since 'imap_search' cannot get UID on POP3
			{
				if((@include_once('Auth/SASL.php')) && (@include_once('Net/POP3.php'))) #TODO - Include them as local files instead of PEAR packages
				{
					$secure = $link['info']['receive_secure'] ? 'ssl://' : ''; #Specify SSL connection if configured so
					$pop3 =& new Net_POP3();

					if($pop3->connect($secure.$link['info']['receive_host'], $link['info']['receive_port'])) #Connect
					{
						if($pop3->login($link['info']['receive_user'], $link['info']['receive_pass'])) #Login
						{
							if(is_array($all = $pop3->getListing())) #Get all listing
							{
								$unique = array(); #Store the unique ID for each message
								foreach($all as $list) $unique[$list['msg_id']] = $list['uidl']; #Remember the unique identifier
							}
						}

						$pop3->disconnect();
					}
				}
			}

			$exist = array(); #List of header md5 sum of mails existing in the mail box

			$field = explode(' ', 'date subject to from unseen'); #List of fields to get
			$short = is_array($unique) && count($unique); #If UID can be used to make queries shorter to the mail server

			for($sequence = 1; $sequence <= $content->Nmsgs; $sequence++) #For all of the messages found in the mail box
			{
				if($current[$unique[$sequence]]) #If the given UID is already stored in the database
				{
					unset($current[$unique[$sequence]]); #Find out list of non existing mails on the server
					continue; #If already stored, move on
				}

				$addresses = array(); #List of 'from', 'to' and 'cc' addresses

				$signature = md5(imap_fetchheader($link['connection'], $sequence)); #Keep a unique signature of the message
				$attributes = array(':user' => $user->id, ':folder' => $folder, ':sequence' => $sequence, ':signature' => $signature, ':uid' => isset($unique[$sequence]) ? $unique[$sequence] : null); #Mail information parameters

				foreach(imap_headerinfo($link['connection'], $sequence) as $key => $value) #Get mail information
				{
					$key = strtolower($key);

					if($key == 'deleted' && $value == 'D') continue 2; #Ignore deleted messages
					if(!in_array($key, $field)) continue; #Ignore other parameters

					if(!is_array($value))
					{
						switch($key)
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
					else
					{
						foreach($value as $index => $inner) #Store the mail addresses
						{
							$parts = array();

							foreach($inner as $name => $text) $parts[$name] = self::decode($text);
							$addresses[$key][] = array(':name' => $parts['personal'] ? $parts['personal'] : null, ':address' => "{$parts['mailbox']}@{$parts['host']}");
						}
					}
				}

				if(!$short) $exist[] = $database->handler->quote($signature); #Keep remembering the header signature

				$query['check']->run(array(':user' => $user->id, ':folder' => $folder, ':signature' => $signature));
				if(!$query['check']->success) return false; #Quit the entire function if it fails

				if($query['check']->column()) #If the mail exists
				{
					foreach($flag as $column => $mark) if($query[$column]) #Flag the states
						$query[$column]->run(array(":$column" => $attributes[':'.strtolower($mark)] ? 0 : 1, ':user' => $user->id, ':folder' => $folder, ':signature' => $signature));

					continue; #Check for the next message (TODO - This avoids having duplicate messages in the mailbox, even possibly with different body)
				}

				$query['insert']->run($attributes); #Insert the mail in the database
				$id = $database->id(); #Id for the mail in the database

				foreach(array('from', 'to', 'cc') as $section) #Insert the addresses
				{
					if(!is_array($addresses[$section])) continue;

					foreach($addresses[$section] as $values)
					{
						$values[':mail'] = $id;
						$query[$section]->run($values);
					}
				}

				#imap_gc($link['connection'], IMAP_GC_ELT & IMAP_GC_ENV & IMAP_GC_TEXTS); #Keep freeing memories - NOTE : Only available in PHP >= 5.3
			}

			if($short) foreach($current as $key => $value) if(strlen($key)) $exist[] = $database->handler->quote($key); #List of UID not existing on the server anymore

			sort($exist); #TODO - Is this necessary for database performance?
			$exist = implode(',', $exist); #Concatenate the identifiers

			#Match on UID for deletion that was not found on the mail server. Otherwise, go through the header hashes for deletion
			$sql = $short ? "uid IN ($exist)" : "signature NOT IN ($exist)";

			$query['select'] = $database->prepare("SELECT id FROM {$database->prefix}mail WHERE user = :user AND folder = :folder AND $sql");
			$query['select']->run(array(':user' => $user->id, ':folder' => $folder)); #Pick non existing mails

			if(!$query['select']->success) return false;

			$association = explode(' ', 'from to cc attachment body reference'); #Message related table names
			$query['delete'] = $database->prepare("DELETE FROM {$database->prefix}mail WHERE id = :id"); #TODO - Should these queries be concatenated as a single query using 'IN'?

			foreach($association as $section) $query[$section] = $database->prepare("DELETE FROM {$database->prefix}$section WHERE mail = :mail");
			$query['reference'] = $database->prepare("DELETE FROM {$database->prefix}reference WHERE reference = :reference");

			foreach($query['select']->all() as $row) #For all of the missing mails
			{
				$query['delete']->run(array(':id' => $row['id'])); #Remove the mail data
				if(!$query['delete']->success) return false;

				$query['reference']->run(array(':reference' => $row['id'])); #Remove the links referred to this mail from another mail
				if(!$query['reference']->success) return false;

				foreach($association as $section) #Remove all of the corresponding mail data
				{
					$query[$section]->run(array(':mail' => $row['id']));
					if(!$query[$section]->success) return false;
				}
			}

			$query = $database->prepare("UPDATE {$database->prefix}folder SET updated = :updated WHERE id = :id AND user = :user");
			$query->run(array(':updated' => date('Y-m-d H:i:s'), ':id' => $folder, ':user' => $user->id));

			return true; #Report success ignoring the last update query's result
		}
	}
?>

<?php
	class Mail_1_0_0_Item
	{
		#Max bytes allowed to be transferred from mail servers as message body
		#No body will be retrieved if it is bigger
		protected static $_max = 300000;

		protected static $_page = 30; #Number of mails to display per page

		#Amount of max (bytes, lines) of a message body to be sent with the list of messages for previewing
		#Any messages that are larger will be truncated to the size and lines
		protected static $_preview = array(500, 5);

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

			foreach(imap_mime_header_decode($string) as $value) #Parse the encoded string and convert if neccessary
			{
				if($value->charset == 'default' || strtolower($value->charset) == 'utf-8') $decoded .= $value->text;
				else $decoded .= mb_convert_encoding($value->text, 'utf-8', $value->charset);
			}

			return $decoded;
		}

		public static function get($account, $folder, $page, $order = 'sent', $reverse = true, System_1_0_0_User $user = null) #Get list of mails
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account) || !$system->is_digit($folder) || !$system->is_digit($page) || preg_match('/\W/', $order)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$descend = $reverse ? ' DESC' : '';
			$start = ($page - 1) * self::$_page;

			$query['all'] = $database->prepare("SELECT id, subject, size, sent, received, preview FROM {$database->prefix}mail WHERE user = :user AND account = :account AND folder = :folder ORDER BY $order$descend LIMIT $start,".self::$_page);
			$query['all']->run(array(':user' => $user->id, ':account' => $account, ':folder' => $folder));

			if(!$query['all']->success) return false;
			foreach(array('from', 'to', 'cc') as $section) $query[$section] = $database->prepare("SELECT name, address FROM {$database->prefix}$section WHERE mail = :mail");

			foreach($query['all']->all() as $row) #Construct the mail information
			{
				$addresses = '';

				foreach(array('from', 'to', 'cc') as $section)
				{
					$query[$section]->run(array(':mail' => $row['id']));
					if(!$query[$section]->success) return false;

					foreach($query[$section]->all() as $line) $addresses .= $system->xml_node($section, $line);
				}

				$xml .= $system->xml_node('mail', $row, $addresses);
			}

			return $xml;
		}

		public static function show($account, $folder, $id) #Get the body of a message
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account) || !$system->is_digit($folder) || !$system->is_digit($id)) return $log->param();
		}

		public static function update($account, $folder, System_1_0_0_User $user = null) #Store any new messages in the local database
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account) || !$system->is_digit($folder)) return $log->param();

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			list($connection, $host, $parameter, $type) = Mail_1_0_0_Account::connect($account, $folder, $user); #Connect to the server
			$list = $mail = ''; #List of XML outputs

			$content = imap_check($connection);
			if(!$content) $log->user(LOG_ERR, "Invalid mailbox '$folder' on host '$host'", 'Check the mail server or configuration');

			$exist = array(); #List of header md5 sum of mails existing in the mail box
			$field = explode(' ', 'date subject to from'); #List of field to get

			#Prepare to check for mail's existence
			$query['check'] = $database->prepare("SELECT id FROM {$database->prefix}mail WHERE signature = :signature AND user = :user");

			foreach(array('from', 'to', 'cc') as $section) #Query to insert mail addresses
				$query[$section] = $database->prepare("INSERT INTO {$database->prefix}$section (mail, name, address) VALUES (:mail, :name, :address)");

			#Mail data insertion query
			$query['insert'] = $database->prepare("INSERT INTO {$database->prefix}mail (user, account, folder, uid, sequence, signature, subject, size, sent, preview) VALUES (:user, :account, :folder, :uid, :sequence, :signature, :subject, :size, :sent, :preview)");

			for($sequence = 1; $sequence <= $content->Nmsgs; $sequence++) #For all of the messages found in the mail box
			{
				$exist[] = $signature = md5(imap_fetchheader($connection, $sequence)); #Keep a unique signature of the message
				$query['check']->run(array(':signature' => $signature, ':user' => $user->id));

				if(!$query['check']->success) return false; #Quit the entire function if it fails
				if($query['check']->column()) continue; #If already stored, check for the next message

				$addresses = array(); #List of 'from', 'to' and 'cc' addresses

				$detail = imap_headerinfo($connection, $sequence); #Get mail information
				$attributes = array(':user' => $user->id, ':account' => $account, ':folder' => $folder, ':sequence' => $sequence, ':signature' => $signature); #Mail information parameters

				foreach($detail as $key => $value)
				{
					$key = strtolower($key);

					if($key == 'deleted' && $value == 'D') continue 2; #Ignore deleted messages
					if(!in_array($key, $field)) continue; #Ignore other parameters

					if(!is_array($value))
					{
						$store = $key == 'date' ? ':sent' : ":$key";
						$attributes[$store] = self::decode(trim($value));

						switch($key)
						{
							case 'date' : $attributes[$store] = $system->date_datetime(strtotime($attributes[$store])); break;

							#Check if the subject has RFC 2047 style (In the way of, "=?" charset "?" encoding "?" encoded-text "?=")
							#If not, detect and convert the subject encoding (Just to keep this client from being 'broken')
							#If it has a mix of RFC 2047 and plain string, assume the plain strings are not outside 'us-ascii' range and needs no conversion
							#also assuming no other client would decode it otherwise
							case 'subject' :
								$formatted = false;

								foreach(imap_mime_header_decode($value) as $mime) if($mime->charset != 'default') $formatted = true;
								if(!$formatted) $attributes[$store] = mb_convert_encoding($attributes[$store], 'utf-8', mb_detect_encoding($attributes[$store]));
							break;
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

				$structure = imap_fetchstructure($connection, $sequence); #Get the mail's structure
				$body = ''; #The message body

				if($structure->type == 1) #If it is a multi part message
				{
					list($data, $main) = self::_dig($structure->parts); #Dig the message and find the body part
					if($data && $data->bytes < self::$_max) $body = imap_fetchbody($connection, $sequence, $main, FT_PEEK); #Get the content as body if it's valid
				}
				elseif($structure->type == 0 && strtolower($structure->subtype) == 'plain' && $structure->bytes < self::$_max) #If it's a single part message having plain text
				{
					$main = '1'; #Body ID for single part message

					$body = imap_fetchbody($connection, $sequence, $main, FT_PEEK); #Get the body on a single part message
					$data = $structure; #Specify its structure
				}

				$attributes[':size'] = $data->bytes; #Total size of the body

				if($body && is_object($data)) #Only if any textual data is found
				{
					switch($data->encoding) #Decode encoded string
					{
						case 3 : $body = base64_decode($body); break;

						case 4 : $body = quoted_printable_decode($body); break;
					}

					foreach($data->parameters as $param) #Check for character set
					{
						if(strtolower($param->attribute) != 'charset' || strtolower($param->value) == 'us-ascii' || strtolower($param->value) == 'utf-8') continue;
						$body = mb_convert_encoding($body, 'utf-8', $param->value); #Convert to UTF8 if it isn't
					}

					$base = substr($body, 0, self::$_preview[0]); #Limit the size of the message body
					$body = preg_replace('/^((.*\n){1,'.self::$_preview[1].'})(.|\n)+$/', '\1', $base); #Limit the lines of the message body

					if($body != $base) $body = rtrim($body).'...'; #Note about the truncation
					$specified = false; #If charset is given in 'Content-Type' or not

					#NOTE : Need to check through entire headers, since if 'charset' was never given, 'imap_fetchstructure' assumes it is 'us-ascii'
					#but on occasions, some mails are encoded in other character sets without specifying a character set, just to keep this client from being 'broken'
					foreach(explode("\n", imap_fetchheader($connection, $sequence)) as $line) #Check through the headers
					{
						if(count($header = explode(':', trim($line), 2)) == 2) $check = strtolower(trim($header[0])) == 'content-type';
						if($check && stristr($line, 'charset=')) $specified = true;
					}

					if(!$specified) $body = mb_convert_encoding($body, 'utf-8', mb_detect_encoding($body)); #If no character set is defined, do auto conversion
					if($type == 'imap') $attributes[':uid'] = imap_uid($connection, $sequence); #Get message unique ID to reference it later
				}

				$attributes[':preview'] = str_replace("\r", '', $body); #Add the body content

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
			}

			$exist = implode("','", $exist); #Concatenate the existing mail signatures

			$query['select'] = $database->prepare("SELECT id FROM {$database->prefix}mail WHERE signature NOT IN ('$exist') AND user = :user AND account = :account AND folder = :folder");
			$query['select']->run(array(':user' => $user->id, ':account' => $account, ':folder' => $folder)); #Pick non existing mails

			if(!$query['select']->success) return false;
			$query['delete'] = $database->prepare("DELETE FROM {$database->prefix}mail WHERE id = :id");

			$association = explode(' ', 'from to cc attachment body reference');

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

			return $query->success;
		}
	}
?>

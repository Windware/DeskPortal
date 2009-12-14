<?php
	class Mail_1_0_0_Item
	{
		#Max bytes allowed to be transferred from mail servers as message body
		#No body will be retrieved if it is bigger
		protected static $_max = 300000;

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

		public static function get($account, $folder, System_1_0_0_User $user = null) #Get list of mails
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account) || !$system->is_text($folder)) return $log->param();
			if(strlen($folder) > 255) return $log->dev(LOG_ERR, 'IMAP folder name must be less than 255 letters', 'Check the parameter passed');

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT * FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $account, ':user' => $user->id)); #Get the account information

			if(!$query->success) return false;
			$info = $query->row();

			list($connection, $host, $parameter) = Mail_1_0_0_Account::connect($account, $folder, $user); #Connect to the server
			$list = $mail = ''; #List of XML outputs

			$content = imap_check($connection);
			if(!$content) $log->user(LOG_ERR, "Invalid mailbox '$folder' on host '$host'", 'Check the mail server or configuration');

			if($info['receive_type'] == 'pop3') #NOTE : For POP3, manually get the unique ID number, since 'imap_uid' does not work properly over POP3
			{
				require_once('Auth/SASL.php'); #NOTE : Need Auth_SASL pear package
				require_once('Net/POP3.php'); #NOTE : Need Net_POP3 pear package

				$secure = $info['receive_secure'] ? 'ssl://' : ''; #Specify SSL connection if configured so
				$pop3 =& new Net_POP3();

				$pop3->connect($secure.$info['receive_host'], $info['receive_port']);
				$pop3->login($info['receive_user'], $info['receive_pass']);

				$unique = array(); #Store the unique ID for each message for POP3
				$all = $pop3->getListing();

				if(is_array($all)) foreach($all as $list) $unique[$list['msg_id']] = $list['uidl'];
				$pop3->disconnect();
			}

			#Do not add these parameters to the outputting XML
			$exclude = explode(' ', 'maildate udate answered draft deleted toaddress fromaddress senderaddress reply_toaddress');

			for($id = 1; $id <= $content->Nmsgs; $id++) #For all of the messages found
			{
				$detail = imap_headerinfo($connection, $id); #Get mail information

				$attributes = array(); #Mail information parameters
				$address = ''; #List of 'to' and 'from' addresses

				foreach($detail as $key => $value)
				{
					$key = strtolower($key);

					if($key == 'deleted' && $value == 'D') continue 2; #Ignore deleted messages
					if($key == 'reply_to' || $key == 'sender') continue; #Ignore these parameters

					#Create mail information XML
					if(!is_array($value))
					{
						$attributes[$key] = self::decode(trim($value));

						#Check if the subject has RFC 2047 style (In the way of, "=?" charset "?" encoding "?" encoded-text "?=")
						#If not, detect and convert the subject encoding (Just to keep this client from being 'broken')
						#If it has a mix of RFC 2047 and plain string, assume the plain strings are not outside 'us-ascii' range and needs no conversion
						if($key == 'subject')
						{
							$formatted = false;

							foreach(imap_mime_header_decode($value) as $mime) if($mime->charset != 'default') $formatted = true;
							if(!$formatted) $attributes[$key] = mb_convert_encoding($attributes[$key], 'utf-8', mb_detect_encoding($attributes[$key]));
						}
					}
					else
					{
						foreach($value as $index => $inner)
						{
							$parts = array();
							foreach($inner as $name => $text) $parts[$name] = self::decode($text);

							switch($key)
							{
								case 'to' : $parts['address'] = self::decode($detail->toaddress); break;

								case 'cc' : $parts['address'] = self::decode($detail->ccaddress); break;

								case 'from' : $parts['address'] = self::decode($detail->fromaddress); break;
							}

							$address .= $system->xml_node($key, $parts); #Add nodes that could have multiple entries
						}
					}
				}

				$structure = imap_fetchstructure($connection, $id); #Get the mail's structure
				$body = ''; #The message body

				if($structure->type == 1) #If it is a multi part message
				{
					list($data, $main) = self::_dig($structure->parts); #Dig the message and find the body part
					if($data && $data->bytes < self::$_max) $body = imap_fetchbody($connection, $id, $main, FT_PEEK); #Get the content as body if it's valid
				}
				elseif($structure->type == 0 && strtolower($structure->subtype) == 'plain' && $structure->bytes < self::$_max) #If it's a single part message having plain text
				{
					$main = '1'; #Body ID for single part message

					$body = imap_fetchbody($connection, $id, $main, FT_PEEK); #Get the body on a single part message
					$data = $structure; #Specify its structure
				}

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
					foreach(explode("\n", imap_fetchheader($connection, $id)) as $line) #Check through the headers
					{
						if(count($header = explode(':', trim($line), 2)) == 2) $check = strtolower(trim($header[0])) == 'content-type';
						if($check && stristr($line, 'charset=')) $specified = true;
					}

					if(!$specified) $body = mb_convert_encoding($body, 'utf-8', mb_detect_encoding($body)); #If no character set is defined, do auto conversion

					if($info['receive_type'] == 'imap') $attributes['id'] = imap_uid($connection, $id); #Get message unique ID to reference it later
					else $attributes['id'] = $unique[$id]; #Use the prefetched unique identifier for POP3

					if(!strlen($attributes['id'])) $attributes['id'] = $id; #If it could not get it, use the sequential number
				}

				$address .= $system->xml_node('body', null, $system->xml_data(str_replace("\r", '', $body))); #Add the body content
				$mail .= $system->xml_node('mail', $attributes, $address, $exclude);
			}

			return $system->xml_node('folder', array('name' => $folder), $mail);
		}

		public static function show($account, $folder, $id) #Get the body of a message
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_digit($account) || !$system->is_text($folder)) return $log->param();
			if(strlen($folder) > 255) return $log->dev(LOG_ERR, 'IMAP folder name must be less than 255 letters', 'Check the parameter passed');

			if($user === null) $user = $system->user();
			if(!$user->valid) return false;

			$database = $system->database('user', __METHOD__, $user);
			if(!$database->success) return false;

			$query = $database->prepare("SELECT * FROM {$database->prefix}account WHERE id = :id AND user = :user");
			$query->run(array(':id' => $account, ':user' => $user->id)); #Get the account information

			if(!$query->success) return false;
			$info = $query->row();

			list($connection, $host, $parameter) = Mail_1_0_0_Account::connect($account, $folder, $user); #Connect to the server
			$list = $mail = ''; #List of XML outputs

			$content = imap_check($connection);
			if(!$content) $log->user(LOG_ERR, "Invalid mailbox '$folder' on host '$host'", 'Check the mail server or configuration');

			if($info['receive_type'] == 'pop3') #NOTE : For POP3, manually get the unique ID number, since 'imap_uid' does not work properly over POP3
			{
				require_once('Auth/SASL.php'); #NOTE : Need Auth_SASL pear package
				require_once('Net/POP3.php'); #NOTE : Need Net_POP3 pear package

				$secure = $info['receive_secure'] ? 'ssl://' : ''; #Specify SSL connection if configured so
				$pop3 =& new Net_POP3();

				$pop3->connect($secure.$info['receive_host'], $info['receive_port']);
				$pop3->login($info['receive_user'], $info['receive_pass']);

				$unique = array(); #Store the unique ID for each message for POP3
				$all = $pop3->getListing();

				if(is_array($all)) foreach($all as $list) $unique[$list['msg_id']] = $list['uidl'];
				$pop3->disconnect();
			}

			#Do not add these parameters to the outputting XML
			$exclude = explode(' ', 'maildate udate answered draft deleted toaddress fromaddress senderaddress reply_toaddress');

			for($id = 1; $id <= $content->Nmsgs; $id++) #For all of the messages found
			{
				$detail = imap_headerinfo($connection, $id); #Get mail information

				$attributes = array(); #Mail information parameters
				$address = ''; #List of 'to' and 'from' addresses

				foreach($detail as $key => $value)
				{
					$key = strtolower($key);

					if($key == 'deleted' && $value == 'D') continue 2; #Ignore deleted messages
					if($key == 'reply_to' || $key == 'sender') continue; #Ignore these parameters

					#Create mail information XML
					if(!is_array($value))
					{
						$attributes[$key] = self::decode(trim($value));

						#Check if the subject has RFC 2047 style (In the way of, "=?" charset "?" encoding "?" encoded-text "?=")
						#If not, detect and convert the subject encoding (Just to keep this client from being 'broken')
						#If it has a mix of RFC 2047 and plain string, assume the plain strings are not outside 'us-ascii' range and needs no conversion
						if($key == 'subject')
						{
							$formatted = false;

							foreach(imap_mime_header_decode($value) as $mime) if($mime->charset != 'default') $formatted = true;
							if(!$formatted) $attributes[$key] = mb_convert_encoding($attributes[$key], 'utf-8', mb_detect_encoding($attributes[$key]));
						}
					}
					else
					{
						foreach($value as $index => $inner)
						{
							$parts = array();
							foreach($inner as $name => $text) $parts[$name] = self::decode($text);

							switch($key)
							{
								case 'to' : $parts['address'] = self::decode($detail->toaddress); break;

								case 'cc' : $parts['address'] = self::decode($detail->ccaddress); break;

								case 'from' : $parts['address'] = self::decode($detail->fromaddress); break;
							}

							$address .= $system->xml_node($key, $parts); #Add nodes that could have multiple entries
						}
					}
				}

				$structure = imap_fetchstructure($connection, $id); #Get the mail's structure
				$body = ''; #The message body

				if($structure->type == 1) #If it is a multi part message
				{
					list($data, $main) = self::_dig($structure->parts); #Dig the message and find the body part
					if($data && $data->bytes < self::$_max) $body = imap_fetchbody($connection, $id, $main, FT_PEEK); #Get the content as body if it's valid
				}
				elseif($structure->type == 0 && strtolower($structure->subtype) == 'plain' && $structure->bytes < self::$_max) #If it's a single part message having plain text
				{
					$main = '1'; #Body ID for single part message

					$body = imap_fetchbody($connection, $id, $main, FT_PEEK); #Get the body on a single part message
					$data = $structure; #Specify its structure
				}

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

					if($body != $base) $body = rtrim($body).' ...'; #Note about the truncation
					$specified = false; #If charset is given in 'Content-Type' or not

					#NOTE : Need to check through entire headers, since if 'charset' was never given, 'imap_fetchstructure' assumes it is 'us-ascii'
					#but on occasions, some mails are encoded in other character sets without specifying a character set, just to keep this client from being 'broken'
					foreach(explode("\n", imap_fetchheader($connection, $id)) as $line) #Check through the headers
					{
						if(count($header = explode(':', trim($line), 2)) == 2) $check = strtolower(trim($header[0])) == 'content-type';
						if($check && stristr($line, 'charset=')) $specified = true;
					}

					if(!$specified) $body = mb_convert_encoding($body, 'utf-8', mb_detect_encoding($body)); #If no character set is defined, do auto conversion

					if($info['receive_type'] == 'imap') $attributes['id'] = imap_uid($connection, $id); #Get message unique ID to reference it later
					else $attributes['id'] = $unique[$id]; #Use the prefetched unique identifier for POP3

					if(!strlen($attributes['id'])) $attributes['id'] = $id; #If it could not get it, use the sequential number
				}

				$address .= $system->xml_node('body', null, $system->xml_data(str_replace("\r", '', $body))); #Add the body content
				$mail .= $system->xml_node('mail', $attributes, $address, $exclude);
			}

			return $system->xml_node('folder', array('name' => $folder), $mail);
		}
	}
?>

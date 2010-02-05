<?php
	class System_1_0_0_Network
	{
		protected static $_cache = 3600; #Amount of seconds to keep DNS caches

		protected static $_redirection = 3; #Amount of redirects allowed

		private static $_content = array(); #Data retrieved when max size is limited

		private static $_exceed = array(); #Flag to note if a stream response exceeded maximum size specified

		private static $_relation = array(); #Data relation to streams

		protected static function _write($channel, $data) #Callback to run on sequential remote request arrival
		{
			static $total; #The total size of retrieved data from the remote source

			$id = intval($channel); #Get the channel ID
			self::$_content[$id] .= $data; #Create the response data

			$length = strlen($data);
			$total[$id] += $length;

			#Return the given length reporting success to retrieve more data
			if($total[$id] <= self::$_relation[$id]['max']) return $length;

			self::$_exceed[$id] = true; #Note that the connection exceeded maximum size specified
			return false; #Drop the connection if the size exceeds
		}

		public static function http(&$system, $request) #Make multiple remote HTTP requests in parallel (TODO - HTTP authentication is not implemented as of now)
		{
			$log = $system->log(__METHOD__);
			if(!is_array($request)) return $log->param();

			$bundle = $content = array(); #List of connections and result contents
			$pool = curl_multi_init(); #Initialize the multiple connection pool

			$conf = $system->app_conf('system', 'static'); #System configuration values

			#Basic curl options to set
			$options = array(CURLOPT_AUTOREFERER => true, #Send referrer page against redirects
			CURLOPT_FAILONERROR => true, #Report failure instead of getting the error content body
			CURLOPT_FILETIME => true, #Get the modified time of the requested content
			CURLOPT_FOLLOWLOCATION => true, #Follow redirections
			CURLOPT_HEADER => true, #Include HTTP headers in the response too
			CURLOPT_CONNECTTIMEOUT => $conf['net_timeout'], #Set the network timeout
			CURLOPT_DNS_CACHE_TIMEOUT => self::$_cache, #Set the valid duration of DNS caches
			CURLOPT_SSL_VERIFYPEER => false, #Do not mind the SSL certificate's validity
			CURLOPT_MAXREDIRS => self::$_redirection, #Amount of max redirects allowed
			CURLOPT_ENCODING => '', #Allow content compression on transfer (Empty string will support 'identity', 'deflate' and 'gzip')
			CURLOPT_USERAGENT => str_replace('%VERSION%', str_replace('_', '.', $system->system['version']), $conf['net_agent'])); #The user agent string to send

			foreach($request as $info) #For all of the page requests
			{
				if(!$system->is_address($info['address'])) continue; #Drop invalid addresses

				$channel = curl_init($info['address']); #Initialize the curl object
				$info['header']['Expect'] = ''; #Avoid using sometimes unsupported 100 HTTP status

				foreach($info['header'] as $key => $request) #Set given headers
				{
					if(!preg_match('/^[a-z\-]+$/i', $key) || !is_string($request)) continue; #Ignore invalid headers

					switch($key) #Use specific curl options where they exist
					{
						case 'Cookie' : curl_setopt($channel, CURLOPT_COOKIE, $request); break; #TODO - Point is?

						case 'If-Modified-Since' : curl_setopt($channel, CURLOPT_TIMEVALUE, strtotime($request)); break; #TODO - Point is?

						case 'Referer' : curl_setopt($channel, CURLOPT_REFERER, $request); break; #TODO - Point is?

						default : curl_setopt($channel, CURLOPT_HTTPHEADER, array("$key: $request")); break;
					}
				}

				switch($info['method'])
				{
					case 'HEAD' : curl_setopt($channel, CURLOPT_NOBODY, true); break; #Go with HEAD request if specified 

					case 'POST' :
						curl_setopt($channel, CURLOPT_POST, true); #Set it as a POST request if specified
						curl_setopt($channel, CURLOPT_POSTFIELDS, $info['post']); #Set the data to send
					break;
				}

				$id = intval($channel); #Channel ID

				if($limit[$id] = $system->is_digit($info['max'])) #If limiting
				{
					self::$_relation[$id] = $info; #Keep the related data array for this stream to be used in the callback
					curl_setopt($channel, CURLOPT_WRITEFUNCTION, array(__CLASS__, '_write')); #Pipe the response through a callback to limit the retieval size
				}
				else curl_setopt($channel, CURLOPT_RETURNTRANSFER, true); #Get the content as a return value instead of outputting directly

				curl_setopt_array($channel, $options); #Set basic parameters
				curl_multi_add_handle($pool, $channel); #Add to the connection pool

				$bundle[$info['address']] = $channel; #Keep the list of connections
			}

			#Run the requests in parallel
			do { $task = curl_multi_exec($pool, $active); } while($task == CURLM_CALL_MULTI_PERFORM);

			while($active && $task == CURLM_OK) if(curl_multi_select($pool) != -1)
				do { $task = curl_multi_exec($pool, $active); } while($task == CURLM_CALL_MULTI_PERFORM);

			foreach($bundle as $address => $channel) #For all the connections
			{
				curl_multi_remove_handle($pool, $channel); #Let go of the connection handler

				$id = intval($channel); #Channel ID
				$body = $limit[$id] ? self::$_content[$id] : curl_multi_getcontent($channel); #Get the response content

				do #Keep checking through redirection headers
				{
					list($state, $body) = preg_split('/(\r?\n){2}/', $body, 2); #Retrieve the received string

					$header = array(); #The response headers
					$status = null; #HTTP status code given

					foreach(explode("\n", $state) as $line) #Not caring "\r" since they will be "trim"ed
					{
						if($status === null && preg_match('|^HTTP/|i', $line)) #Get the HTTP status code
						{
							$status = explode(' ', $line);
							$status = $status[1];

							continue;
						}
						elseif(strstr($line, ':'))
						{
							$info = explode(':', $line, 2);
							$header[strtolower(trim($info[0]))] = trim($info[1]);
						}
					}
				}
				while($header['location']); #If 'Location' header is given, check on the next header response

				#Construct the returning values
				$content[] = array('address' => $address, 'status' => $status, 'header' => $header, 'body' => self::$_exceed[$id] ? '' : $body, 'exceed' => !!self::$_exceed[$id]);
			}

			curl_multi_close($pool); #Destroy the pool
			return $content;
		}
	}
?>

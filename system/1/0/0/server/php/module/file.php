<?php
	class System_1_0_0_File
	{
		public static function package(&$system, $list, $compressed) #Send a package of files in XML containers
		{ #FIXME - Malicious queries can create massive amount of caches (ex : Random 404 requests attached : Avoid caching requests having 404)
			$log = $system->log(__METHOD__);
			if(!is_array($list)) return $log->param();

			sort($list = array_unique($list)); #Remove the duplicate entries and create a sorted list of the requested files
			$id = md5(implode('\\', $list)); #Make a unique id out from the specified list to create a cache for repeated requests

			if($output = $system->cache_get("package/$id.xml", $compressed, $system->system['id'])) #If cache exists, use it.
			{
				$log->dev(LOG_INFO, "Sending cached list of files for ID '$id'");
				return $output; #Return the content
			}

			$log->dev(LOG_INFO, "Creating a list of files for ID '$id'");

			#Get the XML template to fit in the files. Exit with LOG_CRIT if it fails.
			$template = $system->file_read("{$system->self['root']}resource/container.xml", LOG_CRIT);

			#Array to keep the user subscription availability to make sure not to check it,
			#as it requires a database operation for each check, on a same application over and over
			$subscription = array();
			$expanded = array(); #List of expanded files

			#TODO - security good?
			for($i = 0; $i < count($list); $i++) #Expand the wildcard characters in the request and replace the portion of the array with the list of files
			{
				if(!strstr($list[$i], '*')) $expanded[] = $list[$i]; #Look for wildcard characters
				else foreach(glob($system->global['define']['top'].$list[$i]) as $file) $expanded[] = $system->file_relative($file);
			}

			$conf = array('system_static' => $system->app_conf('system', 'static')); #App specific configuration values
			$user = $system->user(); #Get the logged in user if any

			foreach($expanded as $file) #With each request
			{
				if(!preg_match('|^([a-z\d]+)(/\d+){3}/|', $file, $matches))
				{
					$log->dev(LOG_WARNING, "Invalid file request '$file'", 'Send proper file request');
					continue;
				}

				$name = $matches[1]; #Application name
				$major = preg_replace('|^.+?/(\d+).+|', '\1', $file); #Get the major version number

				if(!array_key_exists($name, $subscription)) #Make sure files not subscribed by the user are not sent out
				{
					#Check if the application is public or the user has it subscribed
					$subscription[$name] = $system->app_public($name) || $user->valid && $user->subscribed($name);
				}

				$content = array(); #Array to keep the file information
				$path = $global['define']['top'].$file; #The absolute path to the file

				#If the user is not subscribed or the path is trying to go above, treat as forbidden
				if(!$subscription[$name] || preg_match('!\.\.(/|$)!', $file)) $content['status'] = 403;
				elseif(!$system->file_readable($path)) $content['status'] = 404; #If the file cannot be read, say not found.
				else #Otherwise, deliver the content.
				{
					$log->dev(LOG_INFO, "Building up output for file '$file'");

					$type = $system->file_type($file); #Get the mime type of the file
					if(!is_array($type)) continue; #Drop adding this file with an unknown extension

					$content['mime'] = $type['mime']; #Set its mime type
					$content['status'] = 200; #Report it as found

					if(preg_match('|^text/|i', $content['mime'])) #Do not send out the content of non text files and wrap the content in CDATA section
					{
						$index = "{$name}_$major";
						if(!array_key_exists($index, $conf)) $conf[$index] = $system->app_conf($name, $major);

						$body = $system->file_read($path); #Get the file content
						if($content['mime'] == 'text/javascript') $body = $system->minify_js($body);

						$content['content'] = $system->xml_data(preg_replace('/%conf:(.+?)%/e', '$conf[$index]["$1"]', $body));
					}
				}

				#Build up the XML bits
				$content['file'] = htmlspecialchars($file); #TODO : Might need to care for rare file names that could corrupt the XML (ex : "\n")
				$package .= $system->xml_fill($template, $content);
			}

			$package = $system->xml_format($package); #Create an entire XML representation

			$system->cache_set("package/$id.xml", $package, true, $system->system['id']); #Store the cache including compressed version
			return $compressed ? $system->compress_build($package) : $package; #Send out the package
		}

		public static function read(&$system, $file, $severity = LOG_WARNING) #Read the content of a file and report to log with $severity if failed
		{
			$log = $system->log(__METHOD__);
			$log->dev(LOG_INFO, 'Reading file : '.$system->file_relative($file));

			$content = $system->file_readable($file) ? file_get_contents($file) : false; #Get the file contents

			#If on error, report with the severity level specified or as a WARNING if not specified
			if($content === false) return $log->dev($severity, "File '$file' cannot be read", 'Check for file availability');
			return $content; #Return the content
		}

		public static function store(&$system, $file, $content) #Create a file at a specified location TODO - is this used or safe?
		{
			$log = $system->log(__METHOD__);
			if(!is_string($file) || !is_string($content)) return $log->param();

			$log->dev(LOG_INFO, "Storing file '$file'");
			$folder = dirname($file); #Get the file's folder path

			#Create a container folder if not there. Mode gets masked by umask value
			if(!$system->folder_readable($folder) && !mkdir($folder, 0777, true))
				return $log->dev(LOG_ERR, "Cannot create folder '$folder'", 'Check for the parent folder or file system');

			if(!is_writeable($folder) || !file_put_contents($file, $content)) #Write out the file
				return $log->dev(LOG_ERR, "Failed to write to file '$file'", 'Check for file, folder or file system');

			return true; #Report success on file creation
		}

		public static function type(&$system, $file) #Find a mime type from a file name's extension
		{
			static $_info; #Local cache for file information

			$log = $system->log(__METHOD__);
			if(!is_string($file) || !preg_match('/\.[^\.]+$/', $file)) return $log->param();

			$log->dev(LOG_INFO, "Figuring out the mime type for the file '$file'");

			$ext = preg_replace('/^.+\./', '', $file); #Get the file extension
			if($_info[$ext]) return $_info[$ext]; #Use calculated value if done before

			$mime = $system->file_read("{$system->system['root']}resource/mime.xml", LOG_CRIT); #Read out the mime type list

			#Find the correct mime type from the file extension
			preg_match("|<type ext=\"($ext)\" mime=\"(.+?)\"( compress=\"(.+?)\")? />|i", $mime, $matches);

			#If found, return it
			if($matches[1]) return $_info[$ext] = array('extension' => $matches[1], 'mime' => $matches[2], 'compress' => $matches[4]);

			$problem = "File extension '$ext' cannot be read from the mime file";
			return $log->dev(LOG_ERR, $problem, 'Check for file name extension');
		}
	}
?>

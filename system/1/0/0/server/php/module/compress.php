<?php
	class System_1_0_0_Compress
	{
		public static function build(&$system, $string) #Compresses the given string if possible
		{
			$log = $system->log(__METHOD__);
			if(!$system->compress_ready()) return $string; #If not possible, return as is

			if(function_exists('gzencode')) return gzencode($string); #If builtin function is available, use it
			return $log->dev(LOG_ERR, 'Could not find a method to compress a string', 'Make sure zlib library is enabled', $string);
		}

		public static function header(&$system) #Determine the possibility of compressing and send the header if possible
		{
			$log = $system->log(__METHOD__);

			if($system->global['param']['gzip']) return true; #If already set to gzip, don't try determining again
			if(!$system->compress_possible()) return false; #If either client or server cannot handle gzip, quit

			if(headers_sent()) #If headers are already sent, don't try to gzip
			{
				$problem = 'Cannot set header anymore for gzip';
				return $log->dev(LOG_WARNING, $problem, 'Do not send any content before sending the headers');
			}

			header('Content-Encoding: gzip'); #Send the HTTP header for gzipping
			return $system->global['param']['gzip'] = true; #Keep the gzip state to true
		}

		public static function output(&$system, $string) #Zips the content, add gzip header and return it
		{
			$log = $system->log(__METHOD__);
			if(!is_string($string)) return $log->param('');

			#If gzipping is possible, zip up the passed content and return it
			return $system->compress_header() ? $system->compress_build($string) : $string;
		}

		#Returns if both client and server is capable of gzipping
		public static function possible(&$system) { return $system->compress_ready() && $system->compress_requested(); }

		public static function ready(&$system) #Determines if compression is possible or not on the server side
		{
			static $_possibility; #Remember about gzip capability

			$log = $system->log(__METHOD__);
			if(isset($_possibility)) return $_possibility; #Use calculated value if set

			$log->dev(LOG_INFO, 'Calculating the possibility to use gzip compression');
			$conf = $system->app_conf('system', 'static');

			if(!$conf['compress']) #If not configured in configuration, quit
			{
				$problem = 'Compression disabled in configuration';
				return $_possibility = $log->dev(LOG_NOTICE, $problem, 'Enable "compress" option in the configuration');
			}

			#If gzip function exists, it's possible
			if(function_exists('gzencode') && function_exists('gzinflate')) return $_possibility = true;

			$solution = 'Need PHP with zlib library support'; #If nothing is available
			return $_possibility = $log->dev(LOG_NOTICE, 'No gzipping capability found in the system', $solution);
		}

		public static function requested(&$system) #Figures if the client can handle compressed content
		{
			$log = $system->log(__METHOD__);
			$log->dev(LOG_INFO, 'Finding if the client can handle gzip compression');

			if(!strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) #If the client does not support gzip, report false
				return $log->dev(LOG_NOTICE, 'Client does not support gzip', 'Use a client that supports gzip');

			if(headers_sent()) #If headers are already sent, it's not possible anymore
				return $log->dev(LOG_WARNING, 'Cannot set header anymore for gzip', 'gzip option must be sent in the header');

			return true; #Client should be able to handle gzip. Don't care if client lied.
		}
	}
?>

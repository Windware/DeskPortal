<?php
	class System_1_0_0_Compress
	{
		public static function build(&$system, $string) //Compresses the given string if possible
		{
			$log = $system->log(__METHOD__);
			if(!$system->compress_ready()) return $string; //If not possible, return as is

			if(function_exists('gzencode')) return gzencode($string); //If builtin function is available, use it
			return $log->dev(LOG_ERR, 'Could not find a method to compress a string', 'Make sure zlib library is enabled', $string);
		}

		//http://php.net/manual/en/function.gzdecode.php
		public static function decode($data) //A custom function to decode gzipped data since gzdecode is only available from PHP 5.4.0
		{
			$size = strlen($data);
			if($size < 18 || substr($data, 0, 2) != "\x1f\x8b") return $data; //Not in gzipped format

			$method = ord(substr($data, 2, 1)); //Compression method
			$flags = ord(substr($data, 3, 1)); //Flags

			if($flags & 31 != $flags) return ''; //Reserved bits not allowed

			//NOTE : $mtime may be negative (PHP integer limitations)
			$mtime = unpack('V', substr($data, 4, 4));
			$mtime = $mtime[1];

			$xfl = substr($data, 8, 1);
			$os = substr($data, 8, 1);

			$headerlen = 10;
			$extralen = 0;
			$extra = '';

			if($flags & 4)
			{
				if($size - $headerlen - 2 < 8) return ''; //Invalid

				$extralen = unpack('v', substr($data, 8, 2));
				$extralen = $extralen[1];

				if($size - $headerlen - 2 - $extralen < 8) return ''; //Invalid

				$extra = substr($data, 10, $extralen);
				$headerlen += 2 + $extralen;
			}

			$filenamelen = 0;
			$filename = '';

			if($flags & 8)
			{
				if($size - $headerlen - 1 < 8) return ''; //Invalid

				$filenamelen = strpos(substr($data, $headerlen), chr(0));
				if($filenamelen === false || $size - $headerlen - $filenamelen - 1 < 8) return ''; //Invalid

				$filename = substr($data, $headerlen, $filenamelen);
				$headerlen += $filenamelen + 1;
			}

			$commentlen = 0;
			$comment = '';

			if($flags & 16)
			{
				if($size - $headerlen - 1 < 8) return ''; //Invalid

				$commentlen = strpos(substr($data, $headerlen), chr(0));
				if($commentlen === false || $size - $headerlen - $commentlen - 1 < 8) return ''; //Invalid

				$comment = substr($data, $headerlen, $commentlen);
				$headerlen += $commentlen + 1;
			}

			$headercrc = '';

			if($flags & 2) //2-bytes (lowest order) of CRC32 on header present
			{
				if($size - $headerlen - 2 < 8) return false; //Invalid
				$calccrc = crc32(substr($data, 0, $headerlen)) & 0xffff;

				$headercrc = unpack('v', substr($data, $headerlen, 2));
				$headercrc = $headercrc[1];

				if($headercrc != $calccrc) return ''; //Bad header CRC
				$headerlen += 2;
			}

			//gzip footer
			$datacrc = unpack('V', substr($data, -8, 4));
			$datacrc = sprintf('%u', $datacrc[1] & 0xFFFFFFFF);

			$isize = unpack('V', substr($data, -4));
			$isize = $isize[1];

			//decompression
			$bodylen = $size - $headerlen - 8;
			if($bodylen < 1) return ''; //Implementation bug

			$body = substr($data, $headerlen, $bodylen);
			$data = '';

			if($bodylen > 0)
			{
				if($method == 8) $data = gzinflate($body, $maxlength); //Currently the only supported compression method
				else return '';
			}

			if($isize != strlen($data) || sprintf('%u', crc32($data)) != $datacrc) return ''; //Verify CRC32
			return $data;
		}

		public static function header(&$system) //Determine the possibility of compressing and send the header if possible
		{
			$log = $system->log(__METHOD__);

			if($system->global['param']['gzip']) return true; //If already set to gzip, don't try determining again
			if(!$system->compress_possible()) return false; //If either client or server cannot handle gzip, quit

			if(headers_sent()) //If headers are already sent, don't try to gzip
			{
				$problem = 'Cannot set header anymore for gzip';
				return $log->dev(LOG_WARNING, $problem, 'Do not send any content before sending the headers');
			}

			header('Content-Encoding: gzip'); //Send the HTTP header for gzipping
			return $system->global['param']['gzip'] = true; //Keep the gzip state to true
		}

		public static function output(&$system, $string) //Zips the content, add gzip header and return it
		{
			$log = $system->log(__METHOD__);
			if(!is_string($string)) return $log->param('');

			//If gzipping is possible, zip up the passed content and return it
			return $system->compress_header() ? $system->compress_build($string) : $string;
		}

		//Returns if both client and server is capable of gzipping
		public static function possible(&$system) { return $system->compress_ready() && $system->compress_requested(); }

		public static function ready(&$system) //Determines if compression is possible or not on the server side
		{
			static $_possibility; //Remember about gzip capability

			$log = $system->log(__METHOD__);
			if(isset($_possibility)) return $_possibility; //Use calculated value if set

			$log->dev(LOG_INFO, 'Calculating the possibility to use gzip compression');
			$conf = $system->app_conf('system', 'static');

			if(!$conf['compress']) //If not configured in configuration, quit
			{
				$problem = 'Compression disabled in configuration';
				return $_possibility = $log->dev(LOG_NOTICE, $problem, 'Enable "compress" option in the system configuration');
			}

			if(function_exists('gzencode')) return $_possibility = true; //If gzip function exists, it's possible

			$solution = 'Need PHP with zlib library support'; //If nothing is available
			return $_possibility = $log->dev(LOG_NOTICE, 'No gzipping capability found in the system', $solution);
		}

		public static function requested(&$system) //Figures if the client can handle compressed content
		{
			$log = $system->log(__METHOD__);
			$log->dev(LOG_INFO, 'Finding if the client can handle gzip compression');

			if(!strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) //If the client does not support gzip, report false
				return $log->dev(LOG_NOTICE, 'Client does not support gzip', 'Use a client that supports gzip');

			if(headers_sent()) //If headers are already sent, it's not possible anymore
				return $log->dev(LOG_WARNING, 'Cannot set header anymore for gzip', 'gzip option must be sent in the header');

			return true; //Client should be able to handle gzip. Don't care if client lied.
		}
	}

<?php
	class System_1_0_0_Cache
	{
		protected static $_expire = 604800; #Default cache period in seconds (7 days)

		public static function header(&$system, $expire = null) #Send caching HTTP headers
		{ #TODO - Use Last-Modified or Etag (md5 on cache file) on top of it? - in that case, let the cache expire in 1 day
			$log = $system->log(__METHOD__);

			if(($expire === null || $expire != 0) && !$system->app_conf('system', 'static', 'cache')) return true; #Do not send headers if caching is disabled
			if(headers_sent()) return $log->dev(LOG_ERR, 'Headers already sent', 'Set headers before sending contents');

			if($expire === 0) #If not to be cached
			{
				$end = 'Thu, 01 Jan 1970 00:00:00'; #Set to a time in the past

				header('Pragma: no-cache'); #For HTTP 1.0 clients
				header('Cache-Control: no-cache, no-store');
			}
			else
			{
				if(!$system->is_digit($expire)) $expire = self::$_expire; #Use default seconds for caches to be valid if not set
				$end = preg_replace('/ \+0000$/', '', gmdate('r', time() + $expire)); #Time for the cache expiration

				header("Cache-Control: public, max-age=$expire"); #If to be cached
			}

			header("Expires: $end GMT"); #Set the expire header in either case
			return true;
		}
	}
?>

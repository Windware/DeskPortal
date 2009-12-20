<?php
	class System_1_0_0_Is
	{
		public static function address($address) #Finds if it is an address
		{
			return is_string($address) && preg_match('|^https?://[0-9a-z\-]+|', $address);
		}

		#Finds if it is a color code
		public static function color($color) { return is_string($color) && preg_match('/^[0-9a-f]{6}$/', $color); }

		#Returns if the given string is a valid numerical representation
		public static function digit($subject, $decimal = false, $signed = false)
		{
			$decimal = $decimal ? '(\.\d+)?' : ''; #To allow decimal numbers or not
			$signed = $signed ? '[\+\-]?' : ''; #To allow signed numbers or not

			return !!preg_match("/^$signed\d+$decimal$/", (string)$subject); #Match it with a regular expression
		}

		#Find out if the given string is a language string or not (Ex : 'fr_FR' or 'es' but case insensitive)
		public static function language($subject) { return !!preg_match('/^[a-z]{2}(-[a-z]{2})?$/i', $subject); }

		#Checks if the subject is a string and optionally if it has a length and contains given regular expression characters
		public static function text($subject, $zero = false, $match = null)
		{
			return is_string($subject) && ($zero || (strlen($subject) && ($match === null || preg_match((String) $match, $subject))));
		}

		#Returns if the given string is a valid ticket to be sent from client cookie
		public static function ticket($subject) { return is_string($subject) && preg_match('/^[a-f\d]{32}$/', $subject); }

		#Returns if the string is in a DATE or DATETIME format
		public static function time($subject) { return !!preg_match('/^\d{4}-\d{1,2}-\d{1,2}( \d{1,2}:\d{1,2}:\d{1,2})?$/', $subject); }

		#Finds if the given subject is a type specified or undefined
		public static function type($subject, $type) { return !isset($subject) || gettype($subject) === $type; }
	}
?>

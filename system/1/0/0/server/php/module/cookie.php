<?php
	class System_1_0_0_Cookie
	{
		public static function set(&$system, $key, $value, $period = 0) { setcookie($key, $value, $period, '', '', !!$_SERVER['HTTPS']); } #Sets a cookie
	}
?>

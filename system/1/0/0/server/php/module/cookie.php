<?php

class System_1_0_0_Cookie
{
	public static function set(&$system, $key, $value, $period = 0)
	{
		return setrawcookie($key, $value, $period, '', '', !!$_SERVER['HTTPS']);
	} //Sets a cookie
}

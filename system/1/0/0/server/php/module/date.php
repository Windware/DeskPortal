<?php

class System_1_0_0_Date
{
	private $_system;

	public static function datetime(&$system, $time = null) //Generate DATETIME format string
	{
		$form = 'Y-m-d H:i:s';
		return $system->is_digit($time) ? gmdate($form, $time) : gmdate($form);
	}
}

<?php
	class System_1_0_0_Folder
	{
		public static function readable($folder) { return is_readable($folder) && is_dir($folder); } //Check if a folder is readable
	}

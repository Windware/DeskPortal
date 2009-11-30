<?php
	class Bookmark_1_0_0_Periodic
	{
		public function __construct($id)
		{
			switch($id)
			{
				case 'update' : Bookmark_1_0_0::update(); break; #Update all address statuses
			}
		}
	}
?>

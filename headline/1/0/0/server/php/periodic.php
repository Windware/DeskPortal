<?php

class Headline_1_0_0_Periodic
{
	public function __construct($id)
	{
		switch($id)
		{
			case 'update' :
				Headline_1_0_0::update();
			break; #Update all feeds
		}
	}
}

?>

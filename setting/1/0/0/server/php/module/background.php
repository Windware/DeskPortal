<?php
	class Setting_1_0_0_Background
	{
		public static function get() //Get list of available wallpapers
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			foreach(glob("{$system->system['root']}client/default/common/image/wallpaper/*.jpg") as $image)
				$list .= $system->xml_node('image', array('name' => basename($image)));

			return $list;
		}
	}

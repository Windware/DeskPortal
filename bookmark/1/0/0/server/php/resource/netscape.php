<?php
	class Bookmark_1_0_0_Resource_Netscape
	{
		public $loaded = false; #Whether loading the bookmarks succeeded or not

		public $list = array(); #List of bookmarks

		function __construct($data)
		{
			foreach(explode("\n", $data) as $line) #Parse line by line
			{
				if(preg_match('|<a href="(.+?)".*?>(.+?)</a>|i', $line, $match)) $this->list[$category][] = array('address' => $match[1], 'name' => $match[2]); #Get link
				elseif(preg_match('|<h3.+?>(.+?)</h3>|i', $line, $match)) $category = $match[1]; #Detect folder
				elseif(preg_match('|</dl>|i', $line)) $category = ''; #End of folder group
			}

			$this->loaded = true;
		}
	}
?>

<?php
	class Announce_1_0_0_Item
	{
		public static function get($year, $month, $language = null)
		{
			$system = new System_1_0_0(__FILE__);
			$log = $system->log(__METHOD__);

			if(!$system->is_language($language)) $langauge = 'en'; #Defaults to English

			$conf = $system->app_conf();
			$feed = new Headline_1_0_0_Feed("{$conf['url']}?language=$language"); #Get the news feed content

			if(!$system->is_digit($year) || !$system->is_digit($month)) $target = time(); #Use current time
			else $target = gmmktime(0, 0, 0, $month, 1, $year); #Get the specified time

			$start = gmdate('Y-m-01 00:00:00', $target); #Set start of this month
			$end = gmdate('Y-m-01 00:00:00', strtotime('+1 month', $target)); #Set start of next month

			#Send out current month's news in XML
			return $feed->xml('date >= :start AND date < :end', array(':start' => $start, ':end' => $end));
		}
	}
?>

<?php
	//This script is supposed to run every 5 minutes through scheduling program such as cron to execute periodic events for PHP scripts

	require(dirname(__FILE__).'/../server/init.php');
	System_Static::event_run();

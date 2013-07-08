<?php
	$system = new System_1_0_0(__FILE__);
	$log = $system->log(__FILE__);

	$html = "{$system->self['root']}client/default/common/template/index.html";

	$pref = $system->language_order(); //Find out the language preference
	$fallback = $system->language_file($system->system['id'], 'fallback.html', $_SERVER['QUERY_STRING']);

	//Set the message for client side script disabled clients
	$page = str_replace('%fallback%', rtrim($system->file_read($fallback)), $system->file_read($html));
	$conf = $system->app_conf('system', 'static'); //Load config

	$values = array($conf['brand'], $conf['brand_site'], $conf['root']);
	$system->cache_header(0); //Do not cache the top page for cookie purpose

	//Set information necessary in the cookie
	$system->cookie_set('language', implode(',', $system->language_order()));
	$system->cookie_set('time', time());

	//NOTE : Not compressing as the output size is small enough
	//TODO : cache by language : ex index.html.en
	print str_replace(explode(' ', '%brand% %brand_site% %top%'), $values, $page);

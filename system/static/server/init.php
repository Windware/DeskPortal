<?php
	require(dirname(__FILE__).'/class.php'); #Load the base classes

	if(function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) System_Static::system_strip(); #Remove magic quotes
	$global = &System_Static::$global; #Shortcut to the globally used variables for assigning values

	#param : Various variable parameters
	#define : Static values
	#config : Configuration values

	$global = array(
		#'periodic' : Set to true, when run under periodic scheduler
		#'report' : Force turn off error display till system configuration is read and keep the original value
		#(and possibly turn it back on) to avoid error messages in production environment if 'display_errors' is on
		'param' => array('periodic' => false, 'report' => error_reporting(0)),
		#'id' : Create a random ID for each session to bind logs together
		#'datetime' : Date format to use for DATETIME database format
		'define' => array('id' => md5(mt_rand()), 'datetime' => 'Y-m-d H:i:s'),
		'conf' => array()
	);

	$global['define']['top'] = realpath(dirname(__FILE__).'/../../..').'/'; #The root directory of the whole system

	#Error codes description. (Refers to LOG_* constants defined by PHP internally)
	#Part of log constants are defined from 0 to 7 as
	#LOG_EMERG, LOG_ALERT, LOG_CRIT, LOG_ERR, LOG_WARNING, LOG_NOTICE, LOG_INFO, LOG_DEBUG
	#The system should use from LOG_CRIT to LOG_DEBUG and leave out using anything else for unnecessary complication
	#LOG_EMERG is used for logging SQL queries, LOG_ALERT is unused
	$global['define']['log'] = explode(' ', 'Query Alert Critical Error Warning Notice Info Debug');

	#Parse XML with regular expression and turn it into an array
	#TODO - cache the result but to where? (separate config file with 1 line of file location?)
	$conf = &System_Static::app_conf('system', 'static'); #Read the configuration XML

	if(!is_array($conf)) #Quit if configuration is unreadable, cannot even log
	{
		print 'System Error';
		exit; #TODO - Try to log to syslog
	}

	foreach(array('app_public', 'app_subscribed', 'app_initial') as $name) #Make sure these are arrays
		if(!is_array($conf[$name])) $conf[$name] = array($conf[$name]);

	#Make the relative path absolute - #FIXME : For windows
	if(!preg_match('|^/|', $conf['log_file'])) $conf['log_file'] = $global['define']['top'].$conf['log_file'];

	#Turn the error display back on if it is configured so
	#Any errors above here are force suppressed (except the logging by System_Static_Log) to avoid errors displayed
	if($conf['log_display']) error_reporting($global['param']['report']);

	umask($conf['umask']); #Set the mask value for new file/directory creation
	ini_set('default_socket_timeout', $conf['net_timeout']); #Set socket operation timeout value

	unset($conf, $name, $global); #Let go of temporary variables
?>

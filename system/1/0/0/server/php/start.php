<?php
	$system = new System_1_0_0(__FILE__);
	$log = $system->log(__FILE__);

	if(!$system->is_version($system->global['define']['system'])) //If the version to use is still not determined
	{
		$system->global['define']['system'] = $system->self['version']; //Specify it as current for now.
		$user = $system->user(); //Get logged in user

		//If there is a user logged in and the user defined system version is not the current one, try to switch to it
		if($user->valid && $user->system !== null && $user->system != $system->global['define']['system'])
		{
			if($system->is_app('system', $user->system)) //If the system version actually exists
			{
				$log->system(LOG_INFO, "Switching system version to user specified version '$user->system'");
				$system->global['define']['system'] = $user->system; //Set it as the use version

				//Load the specified version or continue with current version
				$load = "{$system->global['define']['top']}system/".str_replace('_', '/', $system->global['define']['system']).'/server/start.php';

				if($system->file_readable($load)) //If readable, use it
				{
					$system->file_load($load);
					exit;
				}

				$log->dev(LOG_ERR, "Cannot switch to user specified system version '{$user->system}'", 'Check for \'server/start.php\'');
			}
			else //Otherwise keep using the current version
			{
				$problem = 'User does not have a proper system version specified';
				$log->dev(LOG_ERR, $problem, 'Make sure user has a proper system specified in the database');
				//TODO - log to the user db too
			}
		}
	}

	$code = $system->cache_get('library.js', $system->compress_header()); //Create a cached JavaScript from a list of system core JavaScripts and output them

	if(!$code) //If the JavaScript cache cannot be retrieved, create it
	{
		$log->system(LOG_INFO, "Creating a JavaScript cache for system '{$system->self['version']}'");
		$conf = $system->app_conf('system', 'static');

		//Get the base system part of the script
		$static = $system->file_read("{$system->global['define']['top']}system/static/client/start.js", LOG_CRIT)."\n";
		if($conf['system_demo']) $demo = $system->file_conf('system/static/conf/auth/demo.xml'); //Pick the demo user credential

		//Replace variables in the JavaScript
		$source = array('%demo%', '%demo_identity%', '%demo_password%');
		$replacer = array($conf['system_demo'] ? 'true' : 'false', $demo['identity'], $demo['password']);

		foreach(explode(' ', 'brand brand_site brand_info developer developer_site root') as $holder)
		{
			$source[] = "%$holder%";
			$replacer[] = str_replace("'", "\\'", $conf[$holder]);
		}

		$extensions = array(); //List of file type extensions for each languages

		foreach(explode(' ', 'perl php python ruby') as $language) //Gather the available interpreter extensions
			$extensions[] = "$language : '".$conf["ext_$language"]."'";

		//Add file extension names
		array_push($source, '%extension%');
		array_push($replacer, implode(', ', $extensions));

		//Add version specific starting code
		$static .= $system->file_read("{$system->system['root']}client/default/common/script/start.js", LOG_CRIT)."\n";
		$static = str_replace($source, $replacer, $static); //Replace the variable placeholders

		$stored = "{$system->self['root']}client/default/common/script/"; //Start building a concatenated script to cache
		$run = $system->file_read($stored.'info.js', LOG_CRIT); //Load the info script

		//Add up all of the module scripts
		foreach(glob("{$stored}module/*.js") as $script) $run .= $system->file_read($script, LOG_CRIT)."\n";

		//Add the function core and executing scripts at the end
		$run .= str_replace('%init%', $system->file_read($stored.'init.js', LOG_CRIT)."\n", $system->file_read($stored.'run.js', LOG_CRIT)."\n");

		//Insert the code in the start script
		$code = str_replace('%run%', $run, str_replace('%version%', $system->global['define']['system'], $static));
		$code = $system->minify_js($code); //Minify the script

		$system->cache_set('library.js', $code, true); //Store it as a cache (With a compressed version as well)
		$code = $system->compress_output($code); //Compress the script if necessary for output
	}

	$log->system(LOG_INFO, 'Sending out the system JavaScript');

	if(headers_sent()) //If headers are already sent, quit
	{
		$problem = 'Cannot set header anymore to indicate file type and length for JavaScript';
		return $log->dev(LOG_WARNING, $problem, 'Do not send any content before sending the headers');
	}

	header('Content-Type: text/javascript');
	header('Content-Length: '.strlen($code));

	$system->cache_header();
	print $code; //Print out the JavaScript code in either case

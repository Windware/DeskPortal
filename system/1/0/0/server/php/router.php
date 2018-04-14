<?php
$system = new System_1_0_0(__FILE__);
$log = $system->log(__FILE__);

if(!($env = $system->app_env($system->global['define']['self']))) return false; //Check for the file path

$request = $system->global['define']['top'] . $system->global['define']['self']; //The requested file in absolute path
if(!$system->file_readable($request)) return $log->dev(LOG_ERR, 'Cannot read requested file', 'Check for request parameter');

if(!$system->app_public($env['name'])) //If the application is not public
{
	$user = $system->user(); //Get logged in user
	if(!$user->valid) return print $system->xml_send(-1); //If not logged in, quit and send the status code to indicate system failure

	if(!$user->subscribed($env['name'])) //Check if the user is subscribed to the application requested
	{
		$solution = 'Please subscribe to the application to have the access right';
		return $log->user(LOG_ERR, 'Not allowed to use the application', $solution);
	}
}

if(strtolower(preg_replace('/^.+\.([^\.]+)$/', '\1', $system->global['define']['self'])) == 'php') //Check file extension for PHP file
{
	unset($env, $log, $request, $user); //Remove local variables not to taint the following script
	return $system->file_load($system->global['define']['self']); //If the extension is 'php', run it
}

$info = $system->file_type($system->global['define']['self']); //Get the file information
if(!is_array($info)) return false; //Return false on failure to determine its file information

header("Content-Type: {$info['mime']}"); //Type out the header

$conf = $system->app_conf('system', 'static');
$output = $system->file_read($request, LOG_WARNING); //Get the raw file content

//If the output is a JavaScript file, minify it, safely
if($info['mime'] === 'text/javascript') $output = $system->minify_js($output);

if(!$conf['cache'] || !$info['compress']) //If caching is disabled or compression is not required
{
	if($info['compress'] && $conf['compression'] && $system->compress_header()) $output = $system->compress_build($output); //If it can compress, return the compressed content
}
else
{
	$static = 'system_static'; //Use 'static' version of system to make the cache universal
	$key = "file/{$system->global['define']['self']}"; //The cache identification

	$compressor = $conf['compression'] && $system->compress_ready(); //The possibility to compress on the server side

	if($system->compress_header())
	{
		if($cache = $system->cache_get($key, true, $static)) return print $cache; //Print the compressed cache file if available

		$system->cache_set($key, $output, true, $static); //Create the cache
		$output = $system->compress_output($output); //Output the content
	}
}

$system->cache_header(); //Send caching header for raw file outputs

header('Content-Length: ' . strlen($output)); //Send the size header
header('Content-Disposition: inline; filename=' . basename($system->global['define']['self'])); //Set its file name

print $output;

$self.run = function(callback)
{
	var request = $system.network.item($self.info.root + 'resource/supported.json') //Get the supported app list
	if(!request.valid()) throw 'Cannot load the supported application list'

	try
	{ eval('var supported = ' + request.text + ';') } //Load the list of supported apps to search from
	catch(error)
	{ throw 'Failed reading supported app list file : resource/supported.json' }

	if(!$system.is.object(supported)) throw 'Error loading the supported app list'

	for(var app in supported)
	{
		if(!$system.is.app(app) || !$system.is.array(supported[app])) continue

		var version = $global.user.used[app] //Used version of the application
		if(!version) continue //If not used by the user, drop it

		var major = version.replace(/^(\d+)(_\d+){2}$/, '$1') //Get the major version of the application
		if(!$system.array.find(supported[app], major)) continue //If the major (database) version is not supported, drop it

		var id = app + '_' + version //Application ID
		var target = app + '_' + major //Target app and database version

		var option = '<div><label for="%link%"><input name="%target%" type="checkbox" id="%link%" /> %name%</label></div>'
		var values = {
			link  : [$id, 'target', target].join('_'),
			target: target,
			name  : $global.app[id] && $global.app[id].title || id,
		}

		$system.node.id($id + '_target').innerHTML += $system.text.format(option, values)
	}

	$system.node.id($id).style.visibility = '' //Force turn the window visible earlier to have focus on it
	$system.node.id($id + '_form').search.focus() //Focus on the search field

	$system.app.callback($id + '.run', callback)
	if(!option) throw 'Could not find any supported app'
}

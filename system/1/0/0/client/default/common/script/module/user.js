$self.user = new function() //User related class
{
	var _class = $id + '.user'

	var _inactivity = 0 //Amount of minutes the interface is not clicked

	var _refresh = 5 //Amount of minutes for each ticket exchange

	var _timer //Timer to auto logout the user

	var _wait = 30 //Seconds to wait till the user is forced logout after getting alerted

	this.init = function() //Initialize user variables
	{
		$global.user.id = $system.browser.cookie('identity') //Name of the user to be authenticated
		$global.user.ticket = $system.browser.cookie('ticket') //A temporary ticket given through cookie for authorization

		$global.user.language = $system.language.pref() //Set the preferred language to use
		$global.user.pref.format = {
			full     : '%Y% %M% %d% %h%:%N%',
			date     : '%Y% %M% %d%',
			year     : '%Y%',
			month    : '%Y% %M%',
			monthdate: '%M% %d%',
			time     : '%h%:%N%',
		} //Date display format

		if(!$system.is.md5($global.user.ticket)) return //If ticket is not available, quit

		$system.user.info() //Load user configurations
		$system.user.conf($global.user.loaded.concat('system_static')) //Load application configurations
	}

	this.active = function() //Reset the inactivity timer
	{
		if(_timer) _timer = clearTimeout(_timer) //Quit quitting
		_inactivity = 0 //Reset the timer
	}

	this.conf = function(app) //Loads the user's application configuration
	{
		var log = $system.log.init(_class + '.conf')
		if(!$system.is.md5($global.user.ticket)) return false

		if(!$system.is.array(app)) return log.param()
		if(app.length === 0) return false

		log.dev($global.log.debug, 'dev/user/conf')

		var query = {task: 'user.conf', app: []} //URL query parameter list
		for(var i = 0; i < app.length; i++) if($system.is.id(app[i])) query.app.push(app[i])

		var request = $system.network.send($system.info.root + 'server/php/run.php', query, null, null) //Get it from the server
		if(!request.valid()) return false //TODO - Do async

		var section = ['conf', 'window']

		for(var i = 0; i < section.length; i++)
		{
			var list = $system.dom.tags(request.xml, section[i]) //Get the list of application preferences

			for(var j = 0; j < list.length; j++) //Extract the values
			{
				var id = $system.dom.attribute(list[j], 'app') //The application ID
				var node = $global.user[section[i]]

				if(!$system.is.object(node[id])) node[id] = {} //Create the object if it doest not exist

				var value = $system.dom.attribute(list[j], 'value')
				if($system.is.digit(value)) value = Number(value) //Use a real number instead

				node[id][$system.dom.attribute(list[j], 'name')] = value //Set the application preference value
			}
		}

		if($system.browser.os === 'iphone') $global.user.pref.fade = false //Avoid CPU intensive fade effect on iPhone
	}

	this.info = function() //Loads the user's preferences
	{
		var log = $system.log.init(_class + '.info')
		if(!$system.is.md5($global.user.ticket)) return false

		log.dev($global.log.debug, 'dev/user/pref')
		var param = {task: 'user.info', language: $global.user.language}

		var request = $system.network.send($system.info.root + 'server/php/run.php', param, null, null)
		if(!request.valid()) return false

		if($system.dom.status(request.xml) != '0') //If a failed status was sent
		{
			$global.user.id = $global.user.ticket = '' //Reset the user reference

			$system.browser.cookie('identity', '', true) //Let go of the identity cookie
			$system.browser.cookie('ticket', '', true) //Let go of the ticket cookie

			return //Continue on to load the login screen
		}

		var list = $system.dom.tags(request.xml, 'used') //Get the list of application version the user uses

		for(var i = 0; i < list.length; i++) //Extract the values
		{
			var app = $system.dom.attribute(list[i], 'app')
			var version = $system.dom.attribute(list[i], 'version')

			var id = app + '_' + version //Application ID
			var title = $system.dom.attribute(list[i], 'title')

			if(!$global.app[id]) $global.app[id] = {} //Create application informational hash

			$global.app[id].title = title //Set its title
			$global.user.used[app] = version //Set the used version

			//Set the initial displaying application list
			if($system.dom.attribute(list[i], 'loaded') == 1) $global.user.loaded.push(app + '_' + version)

			if(!$global.app[app]) $global.app[app] = {version: {}} //Create available version list
			var available = $system.dom.tags(list[i], 'version')

			for(var j = 0; j < available.length; j++)
			{
				var major = $system.dom.attribute(available[j], 'major')
				var minor = $system.dom.attribute(available[j], 'minor')

				if(!$global.app[app].version[major]) $global.app[app].version[major] = {} //Create major version hash
				$global.app[app].version[major][minor] = $system.dom.attribute(available[j], 'revisions') //Store the amount of revisions available for a version
			}
		}

		return true
	}

	this.load = function() //Loads the user environment
	{
		var log = $system.log.init(_class + '.load')

		var fade = $global.user.pref.fade //Keep the fade preference
		$global.user.pref.fade = false //Turn off fading to avoid slow down when unloading multiple applications

		for(var name in $global.top) if(!name.match(/^system_/) && typeof $global.top[name] !== 'function') $system.app.unload(name) //Unload loaded applications

		$global.user.pref.fade = fade //Restore the value
		var list = $global.user.loaded

		if(!$system.is.array(list) || !list.length) //If the user has no list of which application to load at start
		{
			$system.app.load('launcher_1_0_0') //Load the launcher as the minimal interface
			return log.dev($global.log.warning, 'dev/user/startup', 'dev/user/startup/solution')
		}

		var info = [] //List of info.js address to load
		var logger //Notification app ID to load first

		var load = [] //List of apps to load reordered

		for(var i = 0; i < list.length; i++) //For all of the initially loaded applications
		{
			if(list[i].match(/^notification_/)) logger = list[i]
			else load.push(list[i])

			info.push($system.app.path(list[i]) + 'client/default/common/script/info.js')
		}

		$system.network.fetch(info) //Preload all the info.js at once instead of separately
		if(logger) load.unshift(logger) //Load 'notification' application first, so messages are processed when reported while loading others

		var fade = $global.user.pref.fade //Get the fade preference value
		$global.user.pref.fade = false //Temporarily disable to load multiple apps quicker

		for(var i = 0; i < load.length; i++) if($system.is.id(load[i])) $system.app.load(load[i], null, undefined, true) //Load the apps without fade effect
		$global.user.pref.fade = fade //Recover the value

		if(!$global.demo.mode) //Keep exchanging the ticket to a new one once in a while if it expires
		{
			if(!$global.user.session) $global.user.refresh = setInterval($system.user.refresh, _refresh * 60000)
			if($global.user.pref.logout) setInterval($system.user.timer, 60000) //Set the auto logout timer
		}

		$system.image.wallpaper($global.user.pref.wallpaper) //Set wallpaper

		if($global.demo.mode) //Under demo mode
		{
			var index = $system.gui.alert($id, 'user/user/demo', 'user/user/demo/message') //Show about write operation limitation
			$system.window.raise(index, 10000) //Make sure other windows won't cover this
		}

		return true
	}

	this.logout = function(force) //Logs out an user
	{
		var cookie = $system.array.list('name ticket language time')
		for(var i = 0; i < cookie.length; i++) $system.browser.cookie(cookie[i], '', true) //Remove the cookies

		if(force === true) location.reload() //Reload back to the login screen
	}

	this.refresh = function() //Refresh the ticket (TODO : How to exchange ticket to a new value without breaking?)
	{
		if(!$system.is.md5($global.user.ticket)) return false //If not logged in, don't bother

		var validate = function(request) //Validate the current user session
		{
			if($system.gui.check(request) != -1 || !$global.user.refresh) return

			clearInterval($global.user.refresh) //Stop checking anymore
			delete $global.user.refresh

			$system.user.logout() //Clear all the cookies
		}

		//Update the cookie expire time
		return $system.network.send($system.info.root + 'server/php/run.php', {task: 'user.refresh'}, null, validate)
	}

	this.timer = function() //Logout the user under certain inactive time
	{
		if($global.user.pref.logout <= 0) return true
		if(++_inactivity < $global.user.pref.logout) return true //Increase the inactive time

		$system.gui.alert($id, 'user/user/expire/title', 'user/user/expire/message', undefined, null, [$system.info.template.cancel])
		return _timer = setTimeout($system.app.method($system.user.logout, [true]), _wait * 1000) //Force logout the user
	}
}

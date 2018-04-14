$self.feed = new function()
{
	var _class = $id + '.feed'

	var _list = function(request) //Refresh the feed list with the returned list
	{
		$self.feed.get(request.xml) //Update the main interface
		$self.conf._1_feed() //Update the configuration interface
	}

	this.add = function(input) //Add a new feed
	{
		var log = $system.log.init(_class + '.add')

		if(!$system.is.element(input)) return log.param()
		if(!$system.is.text(input.value)) return false

		var address = input.value.replace(/^feed:\/\//i, 'http://') //Address value to send
		if(!$system.is.address(address)) return false

		var list = function(request)
		{
			_list(request)

			var name = address
			for(var id in __feed) if(__feed[id].address == address) name = __feed[id].description

			var state = $system.dom.status(request.xml) == '0'
			log.user($global.log.notice, state ? 'user/feed/add' : 'user/feed/add/fail', '', [name])

			input.value = '' //Clear out the input field
		}

		$system.network.send($self.info.root + 'server/php/run.php', {task: 'feed.add'}, {address: address}, list)
		return false //Invalidate the form
	}

	this.get = function(xml, callback) //Get list of feeds
	{
		var log = $system.log.init(_class + '.get')

		var list = function(callback, request)
		{
			var xml = $system.is.object(request.xml) && request.xml || request

			var area = $system.node.id($id + '_list') //The list HTML area
			if(!area) return log.dev($global.log.error, 'dev/area', 'dev/area/solution') //Make sure the HTML node exists

			var scroll = area.scrollTop //Remember the scroll amount
			area.innerHTML = '' //Clear the current entries

			var loaded = {} //List of feeds information loaded

			var language = $system.language.strings($id)
			var feeds = $system.dom.tags(xml, 'feed') //List of feeds passed

			var notice = $system.language.strings($id, 'log.xml')

			for(var i = 0; i < feeds.length; i++)
			{
				var id = $system.dom.attribute(feeds[i], 'id')
				if(!$system.is.digit(id)) continue

				var info = $system.dom.attribute(feeds[i], 'description')
				if(!info.length) info = '(' + language.unavailable + ')'

				var newest = $system.dom.attribute(feeds[i], 'newest') //Get the newest entry's time
				var time = newest.match(/^\d+-\d+-\d+ \d+:\d+:\d+$/) ? newest.split(' ')[0].split('-') : []

				//Store the view span information
				loaded[id] = {
					period     : $system.dom.attribute(feeds[i], 'period'),
					year       : time[0],
					month      : time[1],
					week       : Math.floor((time[2] - 1) / 7) + 1,
					day        : time[2],
					description: info,
					address    : $system.dom.attribute(feeds[i], 'address'),
					newest     : newest,
					since      : __feed[id] && __feed[id].since,
				}

				var link = document.createElement('span') //Create a link for the feed
				link.id = $id + '_feed_' + id //Set its ID

				var source = document.createElement('img') //Source site icon
				source.style.cursor = 'pointer'

				$system.image.set(source, $self.info.devroot + 'image/site.png')
				$system.tip.set(source, $id, 'site')

				source.className = $system.info.id + '_icon'
				source.onclick = $system.app.method($self.gui.source, [$system.dom.attribute(feeds[i], 'site')]) //Display the source site

				link.appendChild(source)
				link.appendChild(document.createTextNode(' ' + info)) //Set its name

				$system.event.add(link, 'onmousedown', $system.app.method($system.event.cancel, [link])) //Prevent the window from getting dragged on click
				link.onclick = $system.app.method($self.entry.get, [id, 1, null]) //Set a function on click

				$system.tip.set(link, $id, 'read')
				$system.node.hover(link, $id + '_active')

				if(id == __selected) $system.node.classes(link, $id + '_chosen', true) //Emphasize the selected feed
				area.appendChild(link) //Append to the list

				if(!__feed[id]) continue

				if(__feed[id].since) //If new entries are published, notify the presence : TODO - Until the system is reloaded, this will always display about new entries
				{
					var notice = document.createElement('strong')
					notice.className = $id + '_new'

					$system.node.text(notice, ' ' + language['new'])
					link.appendChild(notice)
				}

				if(!__feed[id].newest || __feed[id].newest == loaded[id].newest) continue //FIXME - If somehow the 'newest' shows older time, this looks wrong
				loaded[id].since = $system.date.create(__feed[id].newest).timestamp() //Remember the last update time

				var open = function(id)
				{
					if(!$system.window.list[$id].displayed.body) $system.tool.hide($id, 'body') //Uncover the body
					$self.entry.get(id)
				}

				$system.gui.notice($id, $system.text.format(notice['user/feed/new'], [info]), $system.app.method(open, [id])) //Show alert on the title bar
				log.user($global.log.notice, 'user/feed/new', '', [info]) //Notify about new entries

				delete __cache[id] //Remove the feed cache
			}

			area.scrollTop = scroll //Recover the scroll amount

			__feed = loaded //Overwrite the internal feed information
			log.user($global.log.info, 'user/feed/get')

			return $system.app.callback(log.origin + '.list', callback)
		}

		if($system.is.object(xml)) return list(callback, xml) //If feed list object is passed
		return $system.network.send($self.info.root + 'server/php/run.php', {task: 'feed.get'}, null, $system.app.method(list, [callback])) //Load the list from the server
	}

	this.remove = function(id) //Remove a feed
	{
		var log = $system.log.init(_class + '.remove')

		if(!$system.is.digit(id)) return log.param()
		if(id == 0) return false

		var language = $system.language.strings($id, 'conf.xml')
		if(!confirm(language.confirm)) return false

		var list = function(request)
		{
			log.user($global.log.notice, 'user/feed/remove', '', [__feed[id] ? __feed[id].description : id])
			if(id == __selected) $system.node.id($id + '_entries').innerHTML = '' //Clean the entry list

			_list(request)
		}

		$system.network.send($self.info.root + 'server/php/run.php', {task: 'feed.remove'}, {id: id}, list)
		return false //Stop the form from submitting
	}

	this.sample = function(callback) //Insert sample feeds into user's database
	{
		var log = $system.log.init(_class + '.sample')

		var language = $system.language.strings($id)
		if(!confirm(language.sample)) return $system.app.callback(log.origin, callback)

		var list = function(callback, request)
		{
			log.user($global.log.notice, 'user/sample')

			$self.feed.get(request.xml) //Update the main interface
			return $system.app.callback(log.origin + '.list', callback)
		}

		return $system.network.send($self.info.root + 'server/php/run.php', {task: 'feed.sample'}, {language: $global.user.language}, $system.app.method(list, [callback]))
	}
}

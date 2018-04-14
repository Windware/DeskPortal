$self.entry = new function()
{
	var _class = $id + '.entry'

	var _lock //Lock the feed from getting displayed while the last operation still taking place

	var _mark = {} //Entry star flag

	this.category = function(id, category) //Set a category for an entry
	{
		var log = $system.log.init(_class + '.category')
		if(!$system.is.digit(id) || !__entry[id] || !$system.is.digit(category)) return log.param()

		delete __cache[__entry[id].feed][__entry[id].page] //Remove cache for the entry's page
		return $system.network.send($self.info.root + 'server/php/run.php', {task: 'entry.category'}, {
			id      : id,
			category: category,
		})
	}

	this.get = function(id, page, callback) //Get list of entries from a feed
	{
		var log = $system.log.init(_class + '.get')

		if(!$system.is.digit(id)) return log.param()
		if(_lock) return false

		var release = function() { return _lock = false } //Release the lock in case the network chokes

		_lock = true //Lock it from getting processed while the last operation taking place
		var timer = setTimeout(release, 5000) //Remove the lock if the network fails for some reason

		if(!$system.is.digit(page)) page = 1
		var options = __filter //Create the option parameter to send from the current filter selection

		//Set the query parameters
		options.task = 'entry.get'
		options.feed = id
		options.page = page

		//Change the look of the selected feed and reset the previously selected feed to normal style
		if(__selected) $system.node.classes($id + '_feed_' + __selected, $id + '_chosen', false)
		$system.node.classes($id + '_feed_' + id, $id + '_chosen', true)

		$system.node.id($id + '_feed_name').innerHTML = ''
		$system.node.id($id + '_feed_name').innerHTML = ' : ' + __feed[id].description

		var language = $system.language.strings($id)

		var area = $system.node.id($id + '_entries') //The entry area
		area.innerHTML = $system.text.format('<span class="%%_spacer">%%</span>', [$id, language.loading]) //Show current state

		var list = function(id, page, options, callback, request)
		{
			var cache = __cache[id][page][options.year][options.month][options.week][options.day][options.marked == '1'][options.unread == '1'][options.category][options.search] = $system.is.object(request.xml) ? request.xml : request
			if($system.is.object(request.xml)) log.user($global.log.info, 'user/entry/get', '', [__feed[id] ? __feed[id].description : id])

			clearTimeout(timer) //Release the lock timer
			if(!$system.is.element(area, 'form')) return release() //Make sure the HTML node exists

			__apply(id, true) //Set the form values from the stored values
			__swap($system.node.id($id + '_selection_span').period.value) //Swap the necessary select form element

			__selected = id //Keep the selected feed ID
			$system.node.hide(area, true)

			var holder = $system.array.list('%index% %category% %star% %subject% %new%') //Template variables
			var entries = $system.dom.tags(cache, 'entry') //List of entries

			area.innerHTML = '' //Clear the current entries
			var display //Current line's date

			for(var i = 0; i < entries.length; i++)
			{
				var time = $system.date.create($system.dom.attribute(entries[i], 'date')) //Published time
				var day = time.format($global.user.pref.format.date)

				if(day != display) //If the current day is not yet displayed
				{
					var show = document.createElement('span') //Create a date line
					show.className = $id + '_date' //Give a styling class

					$system.node.text(show, day) //Set the date
					area.appendChild(show) //Attach to the HTML area

					display = day //Remember the displayed date
				}

				var headline = $system.dom.attribute(entries[i], 'id') //The headline ID

				var line = document.createElement('span') //Create a list line
				line.id = $id + '_line_' + headline

				var replacer = [headline] //Variables to be filled in the template

				var picked = [$system.dom.attribute(entries[i], 'category')] //Configured category of the entry
				var choice = '' //Category list

				var cats = $self.category.get()

				for(var j = 0; j < cats.length; j++) //Create HTML category list
				{
					var selected = (picked == cats[j].id) ? ' selected="selected"' : '' //Pre select the specified category
					choice += $system.text.format('<option value="%%"%%>%%</option>\n', [cats[j].id, selected, $system.text.escape(cats[j].name)])
				}

				replacer.push(choice) //Category

				_mark[headline] = $system.dom.attribute(entries[i], 'rate')
				var star = _mark[headline] == 5 ? 'star' : 'grey' //Set the star image by mark

				replacer.push($system.image.source($id, star + '.png')) //Mark image
				replacer.push($system.text.escape($system.dom.attribute(entries[i], 'subject')))

				$system.event.add(line, 'onmousedown', $system.app.method($system.event.cancel, [line])) //Prevent the window from getting dragged on mouse down event
				line.onclick = $system.app.method($self.gui.view, [headline, $system.dom.attribute(entries[i], 'link'), line])

				if($system.dom.attribute(entries[i], 'seen') == '1') $system.node.classes(line, $id + '_read', true) //Set the read status

				//Set new mark
				if(!__feed[id].since || time.timestamp() <= __feed[id].since) replacer.push('')
				else replacer.push(' <strong class="' + $id + '_new">' + language['new'] + '</strong>')

				$system.node.hover(line, $id + '_active') //Make hover color change IE compatible

				line.innerHTML = $system.text.replace($self.info.template.line, holder, replacer) //Set the subject
				area.appendChild(line) //Append the line to the topic area

				var text = $system.dom.text(entries[i])
				if($system.browser.engine == 'khtml' && text.length == 1) text = '' //Avoid Konqueror from inserting white space in tooltip

				$system.tip.set(line, $id, 'info', [text || '(' + language.none + ')']) //Give a summary tooltip
				__entry[headline] = {feed: id, page: page} //Set entry information
			}

			var paging = document.createElement('p') //Create the page selection line
			paging.className = $id + '_paging'

			var amount = $system.dom.attribute($system.dom.tags(cache, 'amount')[0], 'value') //Number of pages
			if(!$system.is.digit(amount) || amount < 1) amount = 1

			var select = document.createElement('select') //Page selections

			select.id = $id + '_page_selector' //NOTE : IE6 refuses to set 'name' attribute on dynamically created 'select' node
			$system.tip.set(select, $id, 'page')

			select.onchange = $system.app.method($self.gui.page, [id]) //NOTE : IE6 refuses to execute event handlers placed on 'select' node with setAttribute (to use 'this.value')

			var alter = function(amount, select, id) //Alter the page flip selection
			{
				var current = Number(select.value)
				select.value = current + amount

				if(current != select.value) $self.gui.page(id) //If the selection changed, flip the page
			}

			var link = document.createElement('a') //Create the link for previous page

			$system.tip.set(link, $id, 'previous')
			$system.node.text(link, '<< ' + language.previous + ' ')

			link.onclick = $system.app.method(alter, [-1, select, id])
			paging.appendChild(link)

			paging.appendChild(document.createTextNode('['))

			for(var i = 1; i <= amount; i++) //Give selectable page listing
			{
				var option = document.createElement('option')
				option.value = i

				$system.node.text(option, i)
				select.appendChild(option)
			}

			paging.appendChild(select)
			select.value = page

			paging.appendChild(document.createTextNode(' / ' + amount + ']'))
			var link = document.createElement('a') //Create the link for next page

			$system.tip.set(link, $id, 'next')
			$system.node.text(link, ' ' + language.next + ' >>')

			link.onclick = $system.app.method(alter, [1, select, id])

			paging.appendChild(link)
			area.appendChild(paging)

			$self.gui.scroll() //Scroll to the top of the list
			$system.node.fade(area.id, false, release)

			$system.app.callback(_class + '.get.list', callback)
		}

		for(var section in __feed[id]) if(section != 'description' && section != 'address') options[section] = __feed[id][section] || __feed[id][section] == 0 || ''

		if(!__cache[id]) __cache[id] = {} //Create cache container
		if(!__cache[id][page]) __cache[id][page] = {}
		if(!__cache[id][page][options.year]) __cache[id][page][options.year] = {}

		var hash = __cache[id][page][options.year] //Shortcut

		if(!hash[options.month]) hash[options.month] = {}
		if(!hash[options.month][options.week]) hash[options.month][options.week] = {}
		if(!hash[options.month][options.week][options.day]) hash[options.month][options.week][options.day] = {}

		var hash = hash[options.month][options.week][options.day] //Shortcut

		var marked = options.marked == '1'
		var unread = options.unread == '1'

		if(!hash[marked]) hash[marked] = {}
		if(!hash[marked][unread]) hash[marked][unread] = {}
		if(!hash[marked][unread][options.category]) hash[marked][unread][options.category] = {}

		if(hash[marked][unread][options.category][options.search]) return list(id, page, options, callback, hash[marked][unread][options.category][options.search])
		return $system.network.send($self.info.root + 'server/php/run.php', options, null, $system.app.method(list, [id, page, options, callback]))
	}

	this.mark = function(id, node) //Mark a topic
	{
		var log = $system.log.init(_class + '.mark')
		if(!$system.is.digit(id) || !__entry[id]) return log.param()

		delete __cache[__entry[id].feed][__entry[id].page] //Remove cache for the entry's page
		$system.event.cancel(node, $system.event.source(arguments))

		if(_mark[id] == 5)
		{
			var image = 'grey'
			_mark[id] = 0
		}
		else
		{
			var image = 'star'
			_mark[id] = 5
		}

		$system.image.set(node.firstChild, $self.info.devroot + 'image/' + image + '.png') //Swap the image
		return $system.network.send($self.info.root + 'server/php/run.php', {task: 'entry.mark'}, {
			id  : id,
			mode: _mark[id],
		})
	}

	this.show = function(id) //Show a specific entry by ID
	{
		var log = $system.log.init(_class + '.show')
		if(!$system.is.digit(id)) return log.param()

		var display = function(id, request)
		{
			var feed = {node: $system.dom.tags(request.xml, 'feed')[0]} //Feed information of the entry

			feed.id = $system.dom.attribute(feed.node, 'id')
			if(!$system.is.digit(feed.id)) return false

			feed.page = $system.dom.attribute(feed.node, 'page') //The page the entry is included
			if(!$system.is.digit(feed.page) || feed.page < 1) feed.page = 1

			var section = $system.array.list('period year month day week')

			for(var i = 0; i < section.length; i++)
			{
				var value = $system.dom.attribute(feed.node, section[i])
				if($system.is.digit(value)) __feed[feed.id][section[i]] = Number(value)
			}

			__apply(feed.id, true) //Set the display span selection form from the internal hash information

			var form = $system.node.id($self.info.id + '_selection_filter') //Filter selection
			form.category.value = ''

			form.marked.checked = false
			form.unread.checked = false

			form.search.value = '' //Clear the filters to avoid missing the entry
			$self.gui.filter(null, true) //Update the filter options internal information

			//Display the feed entries for the given page and scroll to it
			$self.entry.get(feed.id, feed.page, $system.app.method($self.gui.scroll, [id]))
		}

		return $system.network.send($self.info.root + 'server/php/run.php', {
			task: 'entry.show',
			id  : id,
		}, null, $system.app.method(display, [id]))
	}
}

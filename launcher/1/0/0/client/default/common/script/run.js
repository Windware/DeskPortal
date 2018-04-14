$self.run = function(callback)
{
	$system.node.text($id + '_name', $global.user.id) //Set user name
	$system.node.hide($id + '_name', !$global.user.conf[$id].display) //Show it or not

	var display = function(callback, request) //Get and set the list of applications
	{
		var list = $system.dom.tags(request.xml, 'category')

		if(!list.length)
		{
			var log = $system.log.init(_class + '.list')

			$system.app.callback(_class + '.list.display', callback)
			return log.dev($global.log.warning, 'dev/list', 'dev/list/solution')
		}

		var language = $system.language.strings($id)
		var launcher = '' //Application list in HTML

		var expand = [] //List of expanded categories

		for(var i = 0; i < list.length; i++)
		{
			var category = $system.dom.attribute(list[i], 'name')
			var show = $system.dom.attribute(list[i], 'display')

			var entries = $system.dom.tags(list[i], 'entry')
			var section = '' //This category's entries

			var apps = []

			for(var j = 0; j < entries.length; j++)
			{
				var name = $system.dom.attribute(entries[j], 'name')
				var view = $global.app[name] && $global.app[name].title || name

				if(!$system.is.id(name) || view == '') continue
				if($system.array.find(__exclude, name.split('_', 2)[0])) continue //Remove excluded items

				if($system.dom.attribute(entries[j], 'icon')) //Make icon if it exists
				{
					var image = $system.image.source(name, 'icon.png')
					var icon = '<img class="' + $system.info.id + '_icon" src="' + image + '" /> '
				}
				else var icon = ''

				var remove = $system.dom.attribute(entries[j], 'exclude') == '1'

				apps.push({id: name, title: view, exclude: remove})
				if(!remove) section += $system.text.format($self.info.template.list, {
					icon   : icon,
					app    : name,
					display: view,
				})
			}

			if(section) //If entries are found, create the category
			{
				var values = {category: category, section: show, entries: section}
				launcher += $system.text.format($self.info.template.header, values)

				__apps[category] = {name: show, list: apps}
				if($system.dom.attribute(list[i], 'expand') === '1') expand.push(category) //Remember which categories to expand
			}
		}

		$system.node.id($id + '_box').innerHTML = launcher

		for(var category in __apps) //Give tip to the category links
		{
			var link = [$id, 'category', category].join('_')

			$system.tip.set(link, $id, 'open', [__apps[category].name])
			$system.node.hover(link, $id + '_active') //Make hover style
		}

		for(var i = 0; i < expand.length; i++) $system.node.hide($id + '_list_' + expand[i], false) //Expand the categories
		return $system.app.callback(_class + '.run.display', callback)
	}

	return $system.network.send($self.info.root + 'server/php/run.php', {
		task    : 'run',
		language: $global.user.language,
	}, null, $system.app.method(display, [callback]))
}

$self.display = new function()
{
	var _class = $id + '.display'

	this.load = function(app, version, section, params) //Load specific application's information source
	{
		var log = $system.log.init(_class + '.load')
		if(!$system.is.app(app) || !$system.is.digit(version) || !$system.is.text(section) || !$system.is.object(params)) return log.param()

		if($global.user.used[app].replace(/_(\d+)/g, '') != version) //Compare the major versions
			return log.user($global.log.warning, 'dev/version', 'dev/version/solution', [app])

		var load = app + '_' + $global.user.used[app] //The application ID to load

		if(!$system.app.library(load)) return false //Load the library
		var root = $global.top[load] //Root node for the specific application

		switch(app + '_' + version)
		{
			case 'addressbook_1':
				var run = function()
				{
					$system.node.id(load + '_selection').value = params.groups //Set the group selected

					//Load the group list and display the item window
					root.item.get(params.groups, undefined, $system.app.method(root.item.edit, [params.id]))
				}
				break

			case 'bookmark_1':
				var run = function()
				{
					var items = $system.node.id(load + '_selection').elements //Uncheck all categories
					for(var i = 0; i < items.length; i++) if(items[i].type == 'checkbox') items[i].checked = false

					var groups = params.group.split(',') //Belonging categories for that bookmark

					for(var i = 0; i < groups.length; i++)
					{
						var box = $system.node.id(load + '_box_' + groups[i]) //The category checkbox
						if($system.is.element(box, 'input')) box.checked = true //Check all the related groups
					}

					root.item.get($system.app.method(root.gui.edit, [params.id])) //Load the item list and edit window
				}
				break

			case 'calendar_1' : //Load the schedule of the day
				var run = $system.app.method(root.item.show, params.id.split('-'))
				break

			case 'headline_1':
				switch(section)
				{
					case 'feed' : //When the result is a feed
						var run = $system.app.method(root.entry.get, [params.id]) //Select the feed
						break

					case 'item' : //When the result is an entry
						var run = $system.app.method(root.entry.show, [params.id]) //Show it
						break
				}
				break

			case 'mail_1' :
			case 'todo_1':
				var run = $system.app.method(root.item.show, [params.id])
				break

			case 'memo_1' :
				var run = $system.app.method(root.item.show, [params.id, params.group])
				break

			default :
				return log.dev($global.log.error, 'dev/support', 'dev/support/solution', [app, version])
				break
		}

		if(!$system.node.id(load)) return $system.app.load(load, run, true) //Load the application with the given function queued
		if($system.node.hidden(load)) return $system.tool.fade(load, false, run) //Unhide it

		if(!$system.window.list[load].displayed.body) $system.tool.hide(load, 'body') //Uncover the body
		$system.window.raise(load) //Bring to top

		return run()
	}
}

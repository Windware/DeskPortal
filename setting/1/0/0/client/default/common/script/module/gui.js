$self.gui = new function()
{
	var _class = $id + '.gui'

	var _previous //Previously selected tab

	this.apply = function(form) //Apply the set options
	{
		var log = $system.log.init(_class + '.apply')
		if(!$system.is.element(form, 'form')) return log.param()

		var notify = function() //Notify that the user needs to reload for the change to take effect
		{
			log.user($global.log.notice, 'user/gui/complete')
			$system.gui.alert($id, 'user/gui/change/title', 'user/gui/change/message')
		}

		var values = {
			language   : form.language.value,
			logout     : form.logout.value,
			move       : form.move.value,
			translucent: form.translucent.checked ? 1 : 0,
			fade       : form.fade.checked ? 5 : 0,
			round      : form.round.checked ? 1 : 0,
			resize     : form.resize.checked ? 1 : 0,
			stretch    : form.stretch.checked ? 1 : 0,
			center     : form.center.checked ? 1 : 0,
		}
		$system.network.send($self.info.root + 'server/php/run.php', {task: 'gui.apply'}, values, notify)

		return false //Avoid form submission
	}

	this.history = function() //Shows the update history for the selected system version
	{
		var log = $system.log.init(_class + '.history')

		var form = $system.node.id($id + '_form')
		if(!$system.is.element(form, 'form')) return log.param()

		var node = $id + '_history'
		if($system.node.id(node)) return $system.window.fade(node)

		var selector = document.createElement('select') //Create list of system versions
		var version = $global.app.system.version

		for(var major in version)
		{
			for(var minor in version[major])
			{
				for(var i = 0; i < version[major][minor]; i++)
				{
					var option = document.createElement('option')
					option.value = [major, minor, i].join('_')

					$system.node.text(option, [major, minor, i].join('.'))
					selector.appendChild(option)
				}
			}
		}

		var language = $system.language.strings($id)
		var run = $system.app.method($self.gui.version, [form.system.value]) //Display the selected version

		var body = $self.info.template.history.replace('%value:option%', selector.innerHTML)
		return $system.window.create(node, language['history/system'], body, $self.info.color, $self.info.hover, $self.info.window, $self.info.border, false, undefined, undefined, 500, 200, true, true, true, run, null, true)
	}

	this.swap = function(tab) //Switch to another setting tab
	{
		var log = $system.log.init(_class + '.swap')
		if(!$system.is.text(tab)) return log.param()

		if(_previous)
		{
			$system.node.hide($id + '_region_' + _previous, true) //Hide the last selected tab content
			$system.node.classes($id + '_tab_' + _previous, $id + '_active', false)
		}

		$system.node.fade($id + '_region_' + tab, false) //Show the chosen tab content
		$system.node.classes($id + '_tab_' + tab, $id + '_active', true)

		_previous = tab //Remember the current tab
		if(tab == 'background') $self.background.get() //Load the wallpapers
	}

	this.valid = function(checked) //Disables/enables background options
	{
		var log = $system.log.init(_class + '.valid')
		var form = $system.node.id($id + '_form')

		if(!form.resize.checked) //Unless resizing is enabled, disable streching
		{
			form.stretch.disabled = true
			form.stretch.checked = false
		}
		else form.stretch.disabled = false

		if(form.stretch.checked) //Disable centering if streching is enabled
		{
			form.center.checked = false
			form.center.disabled = true
		}
		else form.center.disabled = false
	}

	this.version = function(version) //Swaps the version for system's history
	{
		var log = $system.log.init(_class + '.version')
		if(!$system.is.version(version)) return log.param()

		$system.node.id($id + '_list').innerHTML = ''
		$system.node.id($id + '_list').appendChild($system.tool.display('system_' + version))
	}
}

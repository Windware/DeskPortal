$self.group = new function()
{
	var _class = $id + '.group'

	var _group

	var _load = function(callback, request) //Load group list XML and cache
	{
		_group = {indexed: {}, ordered: []} //Initialize group lists
		var list = $system.dom.tags(request.xml, 'group') //Get the attributes from the XML

		for(var i = 0; i < list.length; i++)
		{
			var id = $system.dom.attribute(list[i], 'id') //Group ID
			var param = {id: id, name: $system.dom.attribute(list[i], 'name')}

			_group.ordered.push(param)
			_group.indexed[id] = param
		}

		if(typeof callback == 'function') return callback(_group)
		return true
	}

	this.get = function(callback) //Get the current group listing with a callback
	{
		if(_group !== undefined && typeof callback == 'function') return callback(_group)
		return $system.network.send($self.info.root + 'server/php/run.php', {task: 'group.get'}, null, $system.app.method(_load, [callback]))
	}

	this.remove = function() //Removes the chosen group
	{
		var log = $system.log.init(_class + '.remove')

		var group = $system.node.id($id + '_pick_group')
		if(!$system.is.element(group) || !$system.is.digit(group.value)) return log.param()

		var language = $system.language.strings($id, 'conf.xml')
		if(!confirm(language.confirm)) return false

		var removal = function(request)
		{
			log.user($global.log.notice, 'user/group/remove', '', [_group.indexed[group.value] ? _group.indexed[group.value].name : group.value])

			$system.node.id($id + '_pick_name').value = '' //Clear out the input fields
			$system.node.hide($id + '_pick_delete', true) //Remove the delete button

			$self.group.update(request) //Update the group list
			$system.node.id($id + '_pick_group').value = 0 //Set to the top option

			$self.item.get() //Relist the entries
		}

		return $system.network.send($self.info.root + 'server/php/run.php', {task: 'group.remove'}, {group: group.value}, removal)
	}

	this.set = function(form) //Sets a group
	{
		var log = $system.log.init(_class + '.set')
		if(!$system.is.element(form, 'form') || !$system.is.digit(form.group.value) || !$system.is.text(form.name.value, true)) return log.param()

		if(!form.name.value.length) return false

		var update = function(request)
		{
			log.user($global.log.notice, form.group.value != '0' ? 'user/group/update' : 'user/group/create', '', [form.name.value])
			$system.node.hide($id + '_pick_delete', true) //Remove the delete button

			form.name.value = '' //Empty the text box
			$self.group.update(request)
		}

		$system.network.send($self.info.root + 'server/php/run.php', {task: 'group.set'}, {
			id  : form.group.value,
			name: form.name.value,
		}, update)
		return false //Avoid the form from being submitted
	}

	this.update = function(request) //Update the group listing in various locations
	{
		var log = $system.log.init(_class + '.update')
		if(!$system.is.object(request)) return log.param()

		var list = function(group)
		{
			$self.conf._1_group() //Set configuration tab groups
			$self.gui.group($id + '_selection', true) //Set main interface groups

			for(var index in __opened) $self.gui.group($id + '_edit_group_' + index) //Update group on this node
		}

		_load(list, request)
	}
}

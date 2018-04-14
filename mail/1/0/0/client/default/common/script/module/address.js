$self.address = new function()
{
	var _class = $id + '.address'

	this.add = function(address, name) //Add email address to address book app
	{
		var log = $system.log.init(_class + '.add')
		if(!$system.is.text(address) || !$system.is.text(name, true)) return log.param()

		//TODO - It cannot open the address book version the user uses, since it may have different API and node names,
		//which may open a different address book app on top of an already displayed version
		var app = 'addressbook_1_0_0' //The address book version this function supports

		var open = function(address, name)
		{
			var pane = $system.node.id(app + '_edit_0') //New address window
			if(!pane || $system.node.hidden(pane)) $global.top[app].item.edit() //If not loaded or visible, bring it to the front

			$system.window.raise(app + '_edit_0') //Load it to the front
			var form = $system.node.id(app + '_form_edit')

			if(name) form.name.value = name //Load the values in the edit form
			form.mail_main.value = address
		}

		if($system.node.hidden(app))
		{
			$system.node.fade(app)
			$system.window.raise(app)
		}

		return $system.app.load(app, $system.app.method(open, [address, name])) //Load the address book app
	}

	this.list = function(index) //Load the addresses
	{
		var display = function(type, index, request) //Place the address in the area
		{
			if($system.dom.status(request.xml) != '0') return $system.gui.alert($id, 'user/address/list', 'user/address/list/message')

			var append = function(index, user) //Append mail address to the current composing screen
			{
				var form = $system.node.id($id + '_compose_' + index + '_form')
				if(!$system.is.element(form, 'form')) return false

				if(form.to.value) //If any previous entries exist
				{
					var current = form.to.value.split(',') //Current list

					//Avoid adding duplicate addresses
					for(var i = 0; i < current.length; i++) if(current[i].match(RegExp('(^|[\\s,<])' + $system.text.regexp(user.address) + '([\\s,>]|$)'))) return false

					form.to.value += ', ' //Place a separator if any previous entries exist
				}

				form.to.value += user.name + ' <' + user.address + '>' //Add name and address to the receiver list
			}

			var field = $system.node.id($id + '_address_' + index + '_area')
			field.innerHTML = ''

			var list = $system.dom.tags(request.xml, 'address')
			var zone = document.createElement('ul')

			for(var i = 0; i < list.length; i++) //Add addresses to the listing
			{
				var address = $system.dom.attribute(list[i], 'mail_' + type)
				var name = $system.dom.attribute(list[i], 'name')

				var item = document.createElement('li')
				var link = document.createElement('a')

				link.onclick = $system.app.method(append, [index, {address: address, name: name}])
				$system.node.text(link, name + ' (' + address + ')')

				item.appendChild(link)
				zone.appendChild(item)
			}

			field.appendChild(zone)
			return true
		}

		var form = $system.node.id($id + '_address_' + index + '_form')

		if(!$system.is.digit(form.group.value))
		{
			$system.node.id($id + '_address_' + index + '_area').innerHTML = ''
			return false
		}

		return $system.network.send($self.info.root + 'server/php/run.php', {
			task : 'address.list',
			group: form.group.value,
			type : form.type.value,
		}, null, $system.app.method(display, [form.type.value, index]))
	}

	this.open = function(index) //Load registered addresses in the addressbook app
	{
		var log = $system.log.init(_class + '.add')
		if(!$system.is.digit(index)) return log.param()

		var id = $id + '_address_' + index + '_window' //Window ID
		if($system.window.list[id]) return $system.window.fade(id, true, null, true)

		var language = $system.language.strings($id)

		var group = function(index)
		{
			var display = function(index, request)
			{
				if($system.dom.status(request.xml) != '0') return $system.gui.alert($id, 'user/address/list', 'user/address/group/message')

				var form = $system.node.id($id + '_address_' + index + '_form')
				if(!$system.is.element(form, 'form')) return false

				var list = $system.dom.tags(request.xml, 'group')

				for(var i = 0; i < list.length; i++) //List up groups
				{
					var option = document.createElement('option')

					option.value = $system.dom.attribute(list[i], 'id')
					$system.node.text(option, $system.dom.attribute(list[i], 'name'))

					form.group.appendChild(option)
				}
			}

			return $system.network.send($self.info.root + 'server/php/run.php', {task: 'address.open'}, null, $system.app.method(display, [index]))
		}

		return $system.window.create(id, $self.info.title + ' : ' + language.address, $self.info.template.load.replace(/%index%/g, index), 'cccccc', 'ffffff', '333333', '333333', false, undefined, undefined, 300, undefined, false, true, true, $system.app.method(group, [index]), null, true)
	}
}

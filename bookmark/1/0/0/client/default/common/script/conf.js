$self.conf = new function()
{
	var _class = $id + '.conf'

	var _submit = false //Whether bookmark import has been made or not

	this._1_group = function() //Configuration tab action to list the groups in the select form
	{
		var log = $system.log.init(_class + '._1_group')

		var list = function(group)
		{
			var node = $system.node.id($id + '_pick_group') //Select form
			var language = $system.language.strings($id, 'conf.xml')

			group = [{id: 0, name: '(' + language['new'] + ')'}].concat(group.ordered) //Prepend 'new' entry
			node.innerHTML = '' //Remove the previous entries

			for(var i = 0; i < group.length; i++) //Set the category options
			{
				var option = document.createElement('option')

				option.value = group[i].id
				$system.node.text(option, group[i].name)

				node.appendChild(option)
			}
		}

		$self.group.get(list)
	}

	//Set the form action address
	this._2_import = function() { $system.node.id($id + '_import_form').action = $system.network.form($self.info.root + 'server/php/run.php?task=conf._2_import') }

	this.display = function(id) //Change the group selection
	{
		var log = $system.log.init(_class + '.display')
		if(!$system.is.digit(id)) return log.param()

		var show = function(group)
		{
			$system.node.id($id + '_pick_name').value = group.indexed[id] ? group.indexed[id].name : ''
			$system.node.hide($id + '_pick_delete', id == 0) //Display or hide the delete button
		}

		$self.group.get(show) //Get the group listings
	}

	this.imported = function(frame) //Loads when the importing process finishes
	{
		if(!_submit) return //If form is not submitted, quit
		if(!$system.is.element(frame, 'iframe')) return false

		var form = $system.node.id($id + '_import_form')
		form.bookmark.value = ''

		var language = $system.language.strings($id, 'conf.xml')
		var button = form.submit.value //Submit button

		form.submit.value = language.load //Revert the message
		form.submit.disabled = false //Re-enable the submit button

		var body = frame.contentWindow.document.body
		if(!body) return false

		if(body.innerHTML == '0')
		{
			$system.gui.alert($id, 'user/item/import', 'user/item/import/message')

			__group = undefined //Remove group caching
			return $self.run() //Update the listing on success
		}

		$system.gui.alert($id, 'user/item/import/fail', 'user/item/import/fail/message')
		return false
	}

	this.submit = function() //Submit the import form
	{
		var form = $system.node.id($id + '_import_form')
		if(!form.bookmark.value.length) return false

		var language = $system.language.strings($id, 'conf.xml')
		var button = form.submit.value //Submit button

		form.submit.value = language.loading //Change the message
		form.submit.disabled = true //Disable clicking while uploading

		return _submit = true //Note about form submission
	}
}

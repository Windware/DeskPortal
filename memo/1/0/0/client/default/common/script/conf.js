$self.conf = new function()
{
	var _class = $id + '.conf'

	this._1_group = function() //Configuration tab action to list the groups in the select form
	{
		var log = $system.log.init(_class + '._1_group')

		var node = $system.node.id($id + '_pick_group') //Select form
		var language = $system.language.strings($id, 'conf.xml')

		var groups = [{id: 0, name: '(' + language['new'] + ')'}].concat($self.group.get())
		node.innerHTML = '' //Remove the previous entries

		for(var i = 0; i < groups.length; i++) //Set the category options
		{
			var option = document.createElement('option')

			$system.node.text(option, groups[i].name)
			option.value = groups[i].id

			node.appendChild(option)
		}
	}

	this.display = function(id) //Change the group selection
	{
		var log = $system.log.init(_class + '.display')
		if(!$system.is.digit(id)) return log.param()

		var groups = $self.group.get(true) //Get the group listings

		$system.node.id($id + '_pick_name').value = groups[id] ? groups[id].name : ''
		$system.node.hide($id + '_pick_delete', id == 0) //Display or hide the delete button
	}
}

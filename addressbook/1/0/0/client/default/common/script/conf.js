$self.conf = new function()
{
	var _class = $id + '.conf'

	this._1_group = function() //Configuration tab action to list the groups in the select form
	{
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
}

$self.conf = new function()
{
	var _class = $id + '.conf'

	this._1_category = function() //Category tab action
	{
		var log = $system.log.init(_class + '._1_category')
		var language = $system.language.strings($id, 'conf.xml')

		//Get the category listings with the initial new register option
		var cats = [{id: 0, name: '(' + language['new'] + ')'}].concat($self.category.get())
		var node = $system.node.id($id + '_pick_category') //The select form

		if(!$system.is.element(node)) return
		node.innerHTML = '' //Wipe the current category

		for(var i = 0; i < cats.length; i++) //Set the category options
		{
			var option = document.createElement('option')

			$system.node.text(option, cats[i].name)
			option.value = cats[i].id

			node.appendChild(option)
		}
	}

	this.display = function(id) //Display the chosen category information
	{
		var log = $system.log.init(_class + '.display')
		if(!$system.is.digit(id)) return log.param()

		var cats = $self.category.get(true) //Get the category listings

		$system.node.id($id + '_pick_name').value = cats[id] ? cats[id].name : ''
		$system.node.hide($id + '_pick_delete', id == 0) //Display or hide the delete button
	}
}

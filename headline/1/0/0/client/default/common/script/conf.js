$self.conf = new function() //TODO : make user alert on errors
{
	var _class = $id + '.conf'

	this._1_feed = function() //List current feeds
	{
		var log = $system.log.init(_class + '._1_feed')

		var select = $system.node.id($id + '_delete_item') //Select form
		select.innerHTML = '' //Remove the previous entries

		var option = document.createElement('option')

		option.value = 0
		$system.node.text(option, '-----')

		select.appendChild(option)

		for(var id in __feed)
		{
			var option = document.createElement('option')

			option.value = id
			$system.node.text(option, __feed[id].description)

			select.appendChild(option)
		}
	}

	this._2_category = function() //Tab action to list the categories in the select form
	{
		var log = $system.log.init(_class + '._2_category')

		var select = $system.node.id($id + '_pick_category') //Select form
		select.innerHTML = '' //Remove the previous entries

		var language = $system.language.strings($id, 'conf.xml')
		var categories = [{id: 0, name: '(' + language['new'] + ')'}].concat($self.category.get())

		for(var i = 0; i < categories.length; i++) //Set the category options
		{
			var option = document.createElement('option')

			$system.node.text(option, categories[i].name)
			option.value = categories[i].id

			select.appendChild(option)
		}
	}

	this.display = function(id) //Change the category selection
	{
		var log = $system.log.init(_class + '.display')
		if(!$system.is.digit(id)) return log.param()

		var categories = $self.category.get(true) //Get the category listings

		$system.node.id($id + '_pick_name').value = categories[id] ? categories[id].name : ''
		$system.node.hide($id + '_pick_delete', id == 0) //Display or hide the delete button
	}
}

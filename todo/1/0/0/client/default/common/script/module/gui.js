$self.gui = new function()
{
	var _class = $id + '.gui'

	var _page = 10 //Items to display per page

	this.create = function() //Create a new registration window
	{
		var id = $id + '_new'
		var focus = function() { $system.node.id($id + '_name').focus() } //Put focus on the name input field

		if($system.node.id(id)) $system.window.fade(id, true, null, true) //TODO - Do not define color in script below
		else $system.window.create(id, $self.info.title, $self.info.template['new'], 'cccccc', '666666', '111111', '000000', false, undefined, undefined, 400, undefined, true, false, true, focus, null, true)

		$self.category.refresh()
	}

	this.filter = function(mode) //Filter the list
	{
		__display = mode
		$self.item.get() //Refresh the list
	}

	this.remove = function(id) //Remove the info window
	{
		delete __opened[id]
		$system.window.fade($id + '_info_' + id, true, null, true)
	}
}

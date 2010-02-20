
	$self.run = function(callback)
	{
		$self.gui.list(callback); //Set the application list

		$system.node.text($id + '_name', $global.user.id); //Set user name
		$system.node.hide($id + '_name', !$global.user.conf[$id].display); //Show it or not
	}

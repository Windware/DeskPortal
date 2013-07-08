
	$self.run = function(callback)
	{
		var language = $system.language.strings($id);

		for(var i = 0; i < __all.length; i++) //Create the column choice options
		{
			var choice = $self.info.template.choice.replace(/%name%/g, __all[i]).replace('%display%', language[__all[i]]);
			$system.node.id($id + '_choice').innerHTML += choice;
		}

		var displayed = $global.user.conf[$id].column;
		displayed = displayed ? displayed.split(',') : ['name', 'note'];

		for(var i = 0; i < displayed.length; i++)
		{
			var name = $system.node.id($id + '_display_' + displayed[i]);
			if($system.is.element(name)) __column[displayed[i]] = name.checked = true;
		}

		$self.gui.header(); //List groups with an empty selection
		$self.gui.group($id + '_selection', true, '', callback); //List groups with an empty selection

		//Note : Not listing any items by default for privacy concerns
	}

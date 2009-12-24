
	$self.run = function(callback)
	{
		var preferred = $system.language.pref(); //Preferred language used in the interface
		var list = $system.language.supported;

		for(var i = 0; i < list.length; i++) //For each of the languages
		{
			var choice = document.createElement('option'); //Create an option tag

			choice.value = list[i].code; //Find it's abbreviation
			$system.node.text(choice, list[i].name); //Set it's formal name

			if(preferred == choice.value) choice.selected = 'selected'; //If it is the chosen language, keep it selected
			$system.node.id($id + '_option').appendChild(choice); //Add the selection to the interface
		}

		if($system.is.md5($global.user.ticket))
		{
			$system.node.hide($id + '_language'); //Remove the language selector if logged in
			$self.gui.show(); //Open the detail by default when logged in
		}

		$system.app.callback($id + '.run', callback);
	}

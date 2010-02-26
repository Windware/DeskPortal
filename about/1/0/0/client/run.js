
	$self.run = function(callback)
	{
		var preferred = $system.language.pref(); //Preferred language used in the interface
		var list = $system.language.supported;

		var picked; //See if the language matches fully on the options
		var select = $system.node.id($id + '_option');

		for(var i = 0; i < list.length; i++) //For each of the languages
		{
			var choice = document.createElement('option'); //Create an option tag

			choice.value = list[i].code; //Find it's abbreviation
			$system.node.text(choice, list[i].name); //Set it's formal name

			if(preferred == choice.value)
			{
				choice.selected = 'selected'; //If it is the chosen language, keep it selected
				picked = true;
			}

			select.appendChild(choice); //Add the selection to the interface
		}

		if(!picked) //If no language matched, try to check on the major language part
		{
			preferred = preferred.replace(/[\-_].+$/, ''); //Strip language variance string
			for(var i = 0; i < list.length; i++) if(preferred == list[i].code) select.value = preferred;
		}

		if($system.is.md5($global.user.ticket))
		{
			$system.node.hide($id + '_language'); //Remove the language selector if logged in
			$self.gui.show(); //Open the detail by default when logged in
		}

		$system.app.callback($id + '.run', callback);
	}

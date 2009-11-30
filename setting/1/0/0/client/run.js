
	$self.run = function(callback)
	{
		var select = $system.node.id($id + '_language');
		if(!$system.is.array($system.language.supported)) throw 'No supported language list found';

		var language = $system.language.strings($id);

		var supported = [{code : '', name : '(' + language.browser + ')'}];
		supported = supported.concat($system.language.supported); //List of supported languages

		for(var i = 0; i < supported.length; i++)
		{
			var option = document.createElement('option');

			option.value = supported[i].code;
			$system.node.text(option, supported[i].name);

			select.appendChild(option); //Append to the selectable language list
		}

		$self.gui.swap('option'); //Default to option tab

		//Set the current values on the form
		var form = $system.node.id($id + '_form');

		var section = $system.array.list('language move logout'); //Select nodes
		for(var i = 0; i < section.length; i++) form[section[i]].value = $global.user.pref[section[i]];

		var section = $system.array.list('fade round resize stretch center'); //Checkbox nodes
		for(var i = 0; i < section.length; i++) form[section[i]].checked = $global.user.pref[section[i]] != 0;

		var system = $global.app.system.version; //Get list of system versions available
		var selector = form.system; //Version selector

		for(var major in system)
		{
			for(var minor in system[major])
			{
				for(var i = 0; i < system[major][minor]; i++)
				{
					var option = document.createElement('option');
					option.value = [major, minor, i].join('_');

					$system.node.text(option, [major, minor, i].join('.'));
					selector.appendChild(option); //Add the version to be selectable
				}
			}
		}

		if($system.is.address($global.user.pref.wallpaper)) //Show the wallpaper address
			$system.node.id($id + '_wallpaper_address').value = $global.user.pref.wallpaper;

		$self.gui.valid(); //Enables or disables options
		if(typeof callback == 'function') callback();
	}

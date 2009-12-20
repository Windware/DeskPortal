
	$self.log = new function()
	{
		var _class = $id + '.log';

		var _index = 0; //Log line ID

		this.display = function(section, origin, level, message, solution, param_message, param_solution) //Display the log to the screen
		{
			var log = $system.node.id($id + '_log'); //The log display field
			var source = origin.replace(/\..+/, '');

			var hint = track = severity = ''; //Debug helpers

			if($system.is.id(source)) //If it came from a proper application
			{
				var strings = $system.language.strings($system.info.id); //System language strings
				var language = $system.language.strings(source, 'log.xml'); //App specific log strings

				message = language[message] && $system.text.format(language[message].replace(/%%/g, '<strong>%%</strong>'), param_message); //Translate the message
				solution = language[solution] && $system.text.format(language[solution].replace(/%%/g, '<strong>%%</strong>'), param_solution); //Translate the solution

				source = $global.app[source].title; //Set the name
				if($global.user.pref.debug && solution) hint = $system.tip.link($system.info.id, null, 'blank', [solution]) + ' style="cursor : help"';
			}

			if($global.user.pref.debug)
			{
				track = $system.tip.link($system.info.id, null, 'blank', [origin]) + ' style="cursor : help"';
				severity = ' (' + $global.log.names[level] + ')' || '(' + level + ')';
			}

			var param = [$system.date.create().format($global.user.pref.format.time), track, source, severity, hint, message || '(' + strings.none + ')'];
			var show = $system.text.format('[%% : <strong%%>%%</strong>]%% <span%%>%%</span>', param);

			var line = document.createElement('div'); //Create a log line

			line.id = $id + '_line_' + (++_index);
			if(section == 'dev') line.className = $id + '_dev'; //Make the log look different for developer messages

			line.innerHTML = show;
			log.insertBefore(line, log.firstChild);

			$system.node.fade(line.id, false);
			log.scrollTop = '0px'; //Scroll back to the top to notify

			var remove = function(line) //Remove the line after a while if set
			{
				setTimeout($system.app.method($system.node.fade, [line.id, true, null, true]), $global.user.conf[$id].period * 60 * 1000);
			}

			if($global.user.conf[$id].period) remove(line); //Set to remove after a period
		}
	}

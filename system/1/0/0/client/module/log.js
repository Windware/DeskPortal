
	$self.log = new function() //Logging related class
	{
		var _messenger = []; //List of functions to pass log to

		var _sending = false; //Flag to indicate the log is being sent to other functions

		var _logger = function(origin)
		{
			this.origin = origin; //Keep the source of the log

			//Report logs related for the developers. If the log is about an error event, it will be reported to the server side
			this.dev = function(level, message, solution, param_message, param_solution, value)
			{
				return _take('dev', this.origin, level, message, solution, param_message, param_solution, value);
			}

			this.debug = function(message, parameter) //Generate a custom raw message
			{
				var origin = this.origin; //Swap the origin, so that the language file of the system can be used
				this.origin = $id;

				if(!$system.is.text(message) && !$system.is.digit(message))
				{
					var language = $system.language.strings($id);
					message = '(' + language.none + ')';
				}

				if(!$system.is.array(parameter)) parameter = [];

				message = '<span style="cursor : help"' + $system.tip.link($id, null, 'blank', [origin]) + '>' + $system.text.format(message, parameter) + '</span>';
				this.user($global.log.error, 'dev/debug', '', [message]); //Force display the log

				this.origin = origin; //Revert back for later logging
			}

			this.param = function(value) //Report about wrong use of parameters (Not logging the parameter values to avoid log inflation)
			{
				var origin = this.origin; //Swap the origin, so that the language file of the system can be used
				this.origin = $id;

				this.dev($global.log.warning, 'dev/param', 'dev/param/solution', [origin], null);
				this.origin = origin; //Revert back for later logging

				return value;
			}

			this.user = function(level, message, solution, param_message, param_solution, value) //Report logs related for the user
			{
				return _take('user', this.origin, level, message, solution, param_message, param_solution, value);
			}
		}

		var _take = function(section, origin, level, message, solution, param_message, param_solution, value) //Build up the log information
		{
			if(!$global.user.id) section = 'dev'; //If not logged in, keep it as developer log
			if(value === undefined) value = false; //Return 'false' as a default value

			if(!$global.user.pref.debug && section == 'dev' || level > $global.user.pref.log) return value; //If not appropriate, drop reporting

			if(_sending) return value; //Avoid making a loop while logging by simply discarding
			_sending = true; //Set a flag that it is currently logging

			if($global.user.pref.debug) $global.log.add(section, level, message, solution, origin, $id); //Keep the log into the global space
			for(var i = 0; i < _messenger.length; i++) _messenger[i](section, origin, level, message, solution, param_message, param_solution); //Send the log

			_sending = false; //Unlock the logging
			return value; //Return the specified value
		}

		//Create a new log object and notify the start of logging
		//Not using 'caller.name' as it cannot figure the parent object of the function executed
		//Rather, build up the function string in each functions and send it as 'origin'
		this.init = function(origin) { return new _logger(origin); }

		this.report = function(run) { if(typeof run == 'function') _messenger.push(run); } //Add a function to report the logs to
	}

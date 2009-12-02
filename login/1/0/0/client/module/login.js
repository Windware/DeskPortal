
	$self.login = new function()
	{
		var _class = $id + '.login';

		var _wait = 5; //Amount of seconds to wait till each login attempt

		this.alert = null; //Alert window if any triggered by the system

		this.enter = function(form) //Submit the form component for authentication
		{
			var log = $system.log.init(_class + '.enter');
			if(!form.user.value || !form.pass.value) return false;

			form.send.disabled = true; //Disable the submit button for a moment

			var proceed = function(log, name, mode)
			{
				if(mode == 0) //On success
				{
					return location.reload(); //Reload with cookies set to load the user environment

					/* The deprecated non reloading method
					$system.user.init(); //Initialize user variables
					$system.language.reset($system.info.id); //Reset the language cache for the system to the user preferred language's

					//TODO - Remove alerts
					return $system.user.load(); //Load the user environment
					*/
				}

				var errors = $system.array.list(' login error network');
				$system.gui.alert($id, 'login', 'fail/' + errors[mode], 3);

				form.pass.value = ''; //Clear out the password which should have been wrong
				return log.dev($global.log.notice, 'dev/login');
			}

			var retry = function() { form.send.disabled = false; } //Re enable the submit button
			setTimeout(retry, _wait * 1000); //Give a moment till retry

			$self.login.request(form.user.value, form.pass.value, form.keep.checked, $system.app.method(proceed, [log, form.user.value])); //Ask for authentication
			return false; //Stop form submission
		}

		this.request = function(user, pass, keep, callback) //Make a login request remotely
		{
			var log = $system.log.init(_class + '.request');
			if(!$system.is.user(user) || typeof pass != 'string') return typeof callback == 'function' ? callback(1) : 1; //If values are bad, quit

			var process = function(callback, keep, request)
			{
				var mode = $system.is.digit(request.code) ? request.code : 2;

				switch(mode)
				{
					case 0 :
						//If improper cookies were passed
						if(!$system.is.user($system.browser.cookie('name')) || !$system.is.md5($system.browser.cookie('ticket')))
						{
							//Cancel the cookie in case only either of them were valid
							$system.browser.cookie('name', '', true);
							$system.browser.cookie('ticket', '', true);

							log.dev($global.log.notice, 'dev/cookie', 'dev/cookie/solution');
							mode = 2; //Even if the server responds success, report that it is not
						}
					break;

					case 1 : log.dev($global.log.notice, 'dev/login'); break; //If reported failure

					default : //If status is 2 or in case server couldn't report any status
						log.dev($global.log.error, 'dev/system', 'dev/system/solution');
						mode = 2;
					break;
				}

				$global.user.session = !keep; //Keep whether the ticket should expire on session end

				if(typeof callback == 'function') callback(mode);
				return mode; //Return the state code
			}

			//Make request for authentication (Response will set the cookie header) - TODO : Report the last login time/IP
			$system.network.send($self.info.root + 'server/php/front.php', {task : 'login.process'}, {user : user, pass : pass, keep : keep ? 1 : 0}, $system.app.method(process, [callback, keep]));
		}
	}
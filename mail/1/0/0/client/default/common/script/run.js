
	$self.run = function(callback)
	{
		var _interval = 4.5; //Interval in minutes to update the folder listing (NOTE : Make it below 5 minutes for 'POP before SMTP' to work in most cases)

		var run = function(callback)
		{
			var timer = function(callback)
			{
				for(var id in __account)
				{
					var update = $system.app.method($self.folder.get, [id, 1]);
					__timer[id] = setInterval(update, _interval * 60 * 1000); //Get folders updated periodically

					update(); //Run it immediately
				}

				$system.app.callback($id + '.run.timer', callback)
			}

			setTimeout($system.app.method(timer, [callback]), 5000) //Update mails after a little moment to avoid choking other downloads
		}

		$self.account.get(false, null, $system.app.method(run, [callback])); //Get list of accounts
		$system.image.set(document.createElement('img'), $self.info.devroot + 'image/drag.png'); //Preload the mail drag move image
	}

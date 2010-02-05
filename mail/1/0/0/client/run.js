
	$self.run = function(callback)
	{
		var _interval = 4.5; //Interval in minutes to update the folder listing (NOTE : Make it below 5 minutes for 'POP before SMTP' to work in most cases)

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

		$self.account.get(false, null, $system.app.method(timer, [callback])); //Get list of accounts

		var mover = document.createElement('img');
		$system.image.set(mover, $self.info.devroot + 'graphic/drag.png'); //Preload the mail drag move image
	}

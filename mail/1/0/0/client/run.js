
	$self.run = function(callback)
	{
		var timer = function(callback)
		{
			$self.account.timer(); //Update folders periodically
			$system.app.callback($id + '.run.timer', callback)
		}

		$self.account.get(false, null, timer); //Get list of accounts

		var mover = document.createElement('img');
		$system.image.set(mover, $self.info.devroot + 'graphic/drag.png'); //Preload the mail drag move image
	}

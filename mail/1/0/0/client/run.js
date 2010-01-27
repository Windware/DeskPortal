
	$self.run = function(callback)
	{
		$self.account.get(null, callback); //Get list of accounts

		var mover = document.createElement('img');
		$system.image.set(mover, $self.info.devroot + 'graphic/drag.png'); //Preload the mail drag move image
	}

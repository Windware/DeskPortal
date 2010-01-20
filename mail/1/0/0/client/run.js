
	$self.run = function(callback)
	{
		$self.account.get(); //Get list of accounts
		$system.app.callback($id + '.run', callback);

		var mover = document.createElement('img');
		$system.image.set(mover, $self.info.devroot + 'graphic/drag.png'); //Preload the mail drag move image
	}

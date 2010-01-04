
	$self.run = function(callback)
	{
		$self.account.get(); //Get list of accounts
		$system.app.callback($id + '.run', callback);
	}

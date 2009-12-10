
	$self.run = function(callback)
	{
		$self.account.get(); //Get list of accounts
		if(typeof callback == 'function') callback();
	}

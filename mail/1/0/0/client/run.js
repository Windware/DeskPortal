
	$self.run = function(callback)
	{
		$self.account.get(); //Get list of accounts

		//NOTE : Interval is set to below 5 minutes to make POP/IMAP before SMTP work for servers having valid window of 5 minutes
		__update = setInterval($self.folder.update, 280000); //Get folders updated periodically

		$system.app.callback($id + '.run', callback);
	}

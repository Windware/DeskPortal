
	$self.run = function(callback)
	{
		$self.account.get(); //Get list of accounts

		//NOTE : Time is set to 280 seconds interval to make POP/IMAP before SMTP to work for servers having valid window of 5 minutes
		__update = setInterval($self.account.update, 280000); //Get mail updates periodically

		if(typeof callback == 'function') callback();
	}

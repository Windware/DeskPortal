
	$self.run = function(callback)
	{
		$self.item.get(); //List the memo
		if(typeof callback == 'function') callback();
	}

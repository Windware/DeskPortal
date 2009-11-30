
	$self.run = function(callback)
	{
		$self.item.get(); //Get the main table displayed
		if(typeof callback == 'function') callback();
	}

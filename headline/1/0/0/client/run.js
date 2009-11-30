
	$self.run = function(callback)
	{
		var interval = 300000; //Feed request interval

		var now = $system.date.create(); //Get current time
		var form = $system.node.id($id + '_selection_span'); //Span selection form

		for(var i = 2009; i <= now.year(); i++) //Set the year select form : FIXME - Need to find the earliest and last year of the news subscribed feeds
		{
			var option = document.createElement('option');

			option.value = i;
			$system.node.text(option, i);

			form.year.appendChild(option);
		}

		form.year.value = now.year();

		for(var i = 1; i <= 12; i++) //Set the month select form
		{
			var option = document.createElement('option');
			option.value = i;

			$system.node.text(option, $system.date.month.full[i]);
			form.month.appendChild(option);
		}

		form.month.value = now.month();

		for(var i = 1; i <= 31; i++) //Set the day select form
		{
			var option = document.createElement('option');
			option.value = i;

			$system.node.text(option, i);
			form.day.appendChild(option);
		}

		form.day.value = now.date();

		$self.category.get(); //Get the list of categories
		$self.category.update(); //Update the category lists

		$self.feed.get(); //Get the headline list
		__updater = setInterval($self.feed.get, interval); //Do so periodically

		if(typeof callback == 'function') callback();
	}

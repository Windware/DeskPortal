$self.run = function(callback)
{
	var interval = 5 //Feed request interval in minutes

	var now = $system.date.create() //Get current time
	var form = $system.node.id($id + '_selection_span') //Span selection form

	for(var i = 2009; i <= now.year(); i++) //Set the year select form : FIXME - Need to find the earliest and last year of the news subscribed feeds
	{
		var option = document.createElement('option')

		option.value = i
		$system.node.text(option, i)

		form.year.appendChild(option)
	}

	form.year.value = now.year()

	for(var i = 1; i <= 12; i++) //Set the month select form
	{
		var option = document.createElement('option')
		option.value = i

		$system.node.text(option, $system.date.month.full[i])
		form.month.appendChild(option)
	}

	form.month.value = now.month()

	for(var i = 1; i <= 31; i++) //Set the day select form
	{
		var option = document.createElement('option')
		option.value = i

		$system.node.text(option, i)
		form.day.appendChild(option)
	}

	form.day.value = now.date()

	$self.category.get() //Get the list of categories
	$self.category.update() //Update the category lists

	var check = function(callback) //Offer the user sample feeds if no feeds are registered
	{
		for(var id in __feed) return $system.app.callback($id + '.run', callback) //If any feeds are registered, quit here
		return $self.feed.sample(callback) //Provide samples
	}

	$self.feed.get(null, $system.app.method(check, [callback])) //Get the headline list
	__updater = setInterval($self.feed.get, interval * 60 * 1000) //Do so periodically

	return true
}

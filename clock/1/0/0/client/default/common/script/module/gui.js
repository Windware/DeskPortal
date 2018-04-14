$self.gui = new function()
{
	var _previous //Previously displayed date

	this.date = function(set) //Set date on and off
	{
		var log = $system.log.init(_class + '.date')
		$system.node.fade($id + '_date', !set)
	}

	this.set = function() //Set the time of the clock
	{
		var now = $system.date.create() //Get the current time
		var day = now.format($global.user.pref.format.monthdate)

		if($global.user.conf[$id].date !== 0 && day != _previous) $self.gui.date(true)
		$system.node.fade($id + '_digital', false)

		$system.node.text($id + '_date', day) //Set the date
		$system.node.text($id + '_digital', now.format($global.user.pref.format.time)) //Set the time

		_previous = day
	}
}

$self.run = function(callback)
{
	$system.log.report($self.log.display) //Register to show live logs
	if(typeof callback == 'function') callback()
}

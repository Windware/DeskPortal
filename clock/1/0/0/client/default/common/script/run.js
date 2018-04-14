$self.run = function(callback)
{
	if($global.user.conf[$id].date === 0) $system.node.hide($id + '_date', true) //If not displayed, hide it
	$self.gui.set() //Set the time initially

	__timer = setInterval($self.gui.set, 60000) //Update the time every minute
	if(typeof callback == 'function') callback()
}

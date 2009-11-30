
	$self.run = function(callback)
	{
		$system.node.id($id).style.visibility = ''; //Force turn the window visible earlier to have focus on it
		document.forms[$id + '_form'].user.focus(); //Move focus to the login user name field

		if(typeof callback == 'function') callback();
	}

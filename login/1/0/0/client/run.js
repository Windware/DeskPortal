
	$self.run = function(callback)
	{
		$system.node.id($id).style.visibility = ''; //Force turn the window visible earlier to have focus on it
		var form = document.forms[$id + '_form']; //Login form

		if($global.demo.mode) //Set demo user credential
		{
			form.user.value = $global.demo.user;
			form.pass.value = $global.demo.pass;
		}

		form.user.focus(); //Move focus to the login user name field
		$system.app.callback($id + '.run', callback);
	}

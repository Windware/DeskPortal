
%init%

	$self.run = function() //The main function to be loaded upon HTML body element load
	{
		//Register mouse movements for window dragging
		$system.event.add(document.body, 'onmousemove', $system.motion.move);
		$system.event.add(document.body, 'onmouseup', $system.motion.stop);

		$system.event.add(document.body, 'onclick', $system.tip.clear);
		$system.event.add(document.body, 'onclick', $system.user.active);

		/*$system.event.add(document.body, 'onmousedown', $system.gui.clean); //Let go of the contextual menu : TODO - For next version

		//Register right click events for custom menu and disable original menu (Opera 9/10 is impossible to do so) : TODO - Possibly using $system.add.event is better (if menu can be suppressed)
		if($system.browser.engine !== 'presto') document.body.oncontextmenu = $system.app.method($system.gui.menu, [undefined]);
		else //For opera, use 'shift + click' instead
		{
			//TODO : These will capture every keystroke
			document.body.onkeydown = $system.app.method($system.gui.menu, [true]); //Remember about key press
			document.body.onkeyup = $system.app.method($system.gui.menu, [false]); //Forget about key press

			document.body.onclick = $system.app.method($system.gui.menu, [undefined]); //Trap click event
		}*/

		if($system.is.md5($global.user.ticket)) $system.user.load(); //If a ticket is present, try to load the user environment
		else //If the client does not have a ticket to send
		{
			var fade = $global.user.pref.fade;
			$global.user.pref.fade = false; //Turn off fading to avoid slow down when loading multiple applications

			var run = 0; //Count how many apps are loaded
			var recover = function() { if(++run === load.length) $global.user.pref.fade = fade; } //Recover the fade preference value

			var load = ['login_1_0_0', 'about_1_0_0', 'announce_1_0_0']; //List of initially loaded apps
			for(var i = 0; i < load.length; i++) $system.app.load(load[i], recover);
		}

		document.body.style.backgroundColor = '#' + $global.user.pref.background;

		var box = document.createElement('table'); //Have a box to contain the background image (Using table to use 'vertical-align')
		box.className = $id + '_full';

		box.cellPadding = 0;
		box.cellSpacing = 0;

		var body = document.createElement('tbody');
		body.className = $id + '_full';

		var row = document.createElement('tr');

		var cell = document.createElement('td');
		cell.className = $id + '_full';

		//Create 'img' element (Not using 'background' property on 'body', since it is not resizable)
		var background = document.createElement('img');
		background.id = $id + '_background';

		background.onmousedown = function(event) //Avoid the image from getting dragged around
		{
			if(!event) event = window.event;
			return event.button != 0;
		}

		cell.appendChild(background);
		row.appendChild(cell);

		body.appendChild(row);
		box.appendChild(body);

		document.body.appendChild(box);

		$system.image.wallpaper($global.user.pref.wallpaper, false); //Set the wallpaper
		$system.event.add(window, 'onresize', $system.image.fit); //Let the wallpaper resize automatically

		/* TODO - Not yet implemented
		$system.task.run(); //Run the task scheduler
		setInterval($system.task.run, 60000); //Do so periodically
		*/
	}

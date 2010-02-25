
	$self.gui = new function() //Interface related class
	{
		var _class = $id + '.gui';

		var _alerts = {}; //Index for each alert window

		var _index = 0; //Alert window index

		var _last = 5; //Amount of seconds alert will stay visible by default

		var _menu = $id + '_context'; //The contextual menu node ID

		var _pressed = false; //presto engine specific variable to track shift key press

		var _queue = {}; //List of actions to take and messages to show when user clicks on the notification

		var _timer; //Timer for fixing corrupted rendering for IE6

		var _window = $id + '_alert_'; //The name of the alert window

		this.interval = 50; //Number of milliseconds to wait for each 'onmousemove' event to be triggered (Used in 'motion.js' and others)

		this.alert = function(id, title, message, timer, format_title, format_message, file) //Creates an alert box on the user's interface
		{
			var log = $system.log.init(_class + '.alert');
			if(!$system.is.id(id) || !$system.is.text(title) || !$system.is.text(message)) return log.param();

			if(file === undefined) file = 'log.xml';
			var language = $system.language.strings(id, file); //Get the language strings

			if(!language || !language[title] || !language[message])
				return log.dev($global.log.error, 'dev/language', 'dev/language/solution', [id, title, message]);

			if(!_alerts[title + message]) _alerts[title + message] = _window + (++_index); //The alert window ID
			else if($system.node.id(_alerts[title + message])) return $system.window.raise(_alerts[title + message]); //Do not make a duplicate warning

			if(!$system.is.digit(timer)) timer = _last;
			var pane = _alerts[title + message];

			//Convert the placeholders
			var target = {subject : $system.text.format(language[title], format_title), message : $system.text.format(language[message], format_message).replace(/%index%/, pane), window : pane};
			var body = $system.text.format($system.info.template.alert, target);

			//Create the alert window - TODO Do not define color in script : maybe put the info in style.js
			$system.window.create(pane, language[title], body, '333333', '777777', 'ddcc55', '555555', false, undefined, undefined, 400, undefined, true, false, undefined, undefined, undefined, true);

			if(timer > 0) setTimeout($system.app.method($system.window.fade, [pane, true, null, true]), timer * 1000); //Fade the alert away
			return pane; //Return the window ID
		}

		this.check = function(request) //Check the return status of the request and report on error
		{
			var code = Number($system.dom.status(request.xml)); //Return code
			if(!$system.is.digit(code, true)) return false;

			switch(code)
			{
				case -1 : //Session error
					var title = 'user/session/error/title';
					var message = 'user/session/error/message';
				break;

				default : return code; break; //Let individual handle the situations
			}

			$system.gui.alert($id, title, message); //Show alert
			return code;
		}

		this.clear = function(node, event) //Function to remove tip on scroll (And fix IE6 bug having selection box corrupted on scroll)
		{
			if(__tip.on) $system.tip.clear(); //If tip is on, remove it on scroll
			if($system.browser.engine != 'trident' || $system.browser.version > 6) return true; //Only do the fix for IE6

			if(!$system.is.element(node)) return false;
			var clear = function(node) { node.className = node.className; } //Let the browser redraw the node redefining its style

			if(_timer) clearTimeout(_timer); //Cancel previous timer
			_timer = setTimeout($system.app.method(clear, [node]), 200); //Do it only once in a while to avoid performance hit
		}

		/*this.imitate = function(frame, pane) //Imitates the event handlers on 'body' for other 'body' inside 'iframe'
		{ TODO - Not implemented
			var log = $system.log.init(_class + '.imitate');
			if(!$system.is.element(frame, 'iframe') || !$system.node.id(pane)) return log.param();

			var body = frame.contentWindow.document.body; //The body element inside the frame

			//Register handlers to copy the behavior partially from the real 'body'
			$system.event.add(body, 'onmousemove', $system.motion.move); //FIXME - Window moves weirdly
			$system.event.add(body, 'onmouseup', $system.motion.stop);
			$system.event.add(body, 'onmousedown', $system.app.method($system.motion.start, [pane, false]));
		}*/

		/*this.menu = function(key, event) //Create a custom contextual menu : TODO - Not implemented yet
		{
			var log = $system.log.init(_class + '.menu');

			if($system.browser.engine == 'presto') //Workaround for opera
			{
				if(key !== undefined)
				{
					if(event.keyCode == 16) _pressed = key; //Remember the pressed state
					return true; //Do not proceed any more for key events
				}
				else if(!_pressed || event.button != 0) return true; //Only while pressed and left clicked
			}

			var position = $system.event.position(event);

			if($system.node.id(_menu))
			{
				$system.node.id(_menu).style.left = position.x + 'px';
				$system.node.id(_menu).style.top = position.y + 'px';

				$system.window.raise(_menu); //Create and bring to foremost
				$system.window.fade(_menu, false);
			}
			else
			{
				$system.window.create(_menu, 'menu', 'im your new contextual menu', 'ffeeaa', '333333', false, position.x, position.y, 100, 200, false, false, true, null, null, true);
				$system.window.raise(_menu); //Create and bring to foremost

				$system.window.list[_menu].locked = true; //Don't let it get moved or resized
			}

			return false; //Cancel original context menu
		}*/

		this.notice = function(id, message, action, remove) //Make or remove an alert icon on the application window's toolbar
		{
			var log = $system.log.init(_class + '.notice');

			if(!$system.is.text(id) || !$system.is.text(message)) return log.param();
			if(!$system.window.list[id]) return false; //Only allow notification on an existing window

			var run = function(id)
			{
				var node = $system.node.id($system.info.id + '_' + id + '_notice');
				var queue = _queue[id].pop(); //Remove the queue

				if(!queue) return;
				if(typeof queue.action == 'function') queue.action(); //Run it

				if(!_queue[id].length) $system.node.hide(node, true); //Let go of the notice icon when the queue is empty
				else
				{
					var display = []; //Concatenate list of messages
					for(var i = _queue[id].length - 1; i >= 0; i--) display.push(_queue[id][i].message);

					$system.tip.set(node, $system.info.id, 'blank', [display.join('\n')], true); //Set a message
				}
			}

			if(!$system.is.array(_queue[id])) _queue[id] = [];
			var node = $system.node.id($system.info.id + '_' + id + '_notice');

			var found = false; //Check for duplicate messages
			for(var i = 0; i < _queue[id].length; i++) if(_queue[id][i].message == message) found = i;

			if(remove !== true) //When setting a new notification
			{
				if(found === false) _queue[id].push({message : message, action : action}); //Queue up the message and the action if a new message or action is specified
				node.onclick = $system.app.method(run, [id]); //Set a callback function when clicking the notification

				var display = []; //Concatenate list of messages
				for(var i = _queue[id].length - 1; i >= 0; i--) display.push(_queue[id][i].message);

				$system.tip.set(node, $system.info.id, 'blank', [display.join('\n')], true); //Set a message
				$system.node.hide(node, false); //Show the indicator
			}
			else if(found) //Only remove the specific message when asked to turn off
			{
				delete _queue[id][found]; //If matches on existing queue, remove the message and the action
				if(!_queue[id].length) $system.node.id(node, true); //Hide the indicator if no more messages are set
			}

			return true;
		}

		/*this.remove = function() //Remove the context menu TODO - Not implemented yet
		{
			if(!$system.node.id(_menu)) return true;
			if(!$system.node.hidden(_menu)) $system.window.fade(_menu);
		}*/

		this.select = function(mode) //Enables or disables text selection
		{
			if($system.browser.os == 'iphone') return true;

			if(!mode) //Disable
			{
				if(document.body.onselectstart !== undefined) document.body.onselectstart = function() { return false; } //IE
				else document.body.onmousedown = function() { return false; } //Non IE
			}
			else //Enable
			{
				if(document.body.onselectstart !== undefined) document.body.onselectstart = function() { return true; } //IE
				else document.body.onmousedown = function() { return true; } //Non IE
			}
		}
	}


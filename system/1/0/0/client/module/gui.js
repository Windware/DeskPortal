
	$self.gui = new function() //Interface related class
	{
		var _class = $id + '.gui';

		var _alerts = {}; //Index for each alert window

		var _index = 0; //Alert window index

		var _menu = $id + '_context'; //The contextual menu node ID

		var _pressed = false; //presto engine specific variable to track shift key press

		var _timer; //Timer for fixing corrupted rendering for IE6

		var _window = $id + '_alert_'; //The name of the alert window

		this.alert = function(id, title, message, timer, format_title, format_message, file) //Creates an alert box on the user's interface
		{
			var log = $system.log.init(_class + '.alert');
			if(!$system.is.id(id) || !$system.is.text(title) || !$system.is.text(message)) return log.param();

			if(file === undefined) file = 'strings.xml';
			var language = $system.language.strings(id, file); //Get the language strings

			if(!language || !language[title] || !language[message])
				return log.dev($global.log.error, 'dev/language', 'dev/language/solution', [id, title, message]);

			if(!_alerts[title + message]) _alerts[title + message] = _window + (++_index); //The alert window ID
			else if($system.node.id(_alerts[title + message])) return true; //Do not make a duplicate warning

			var pane = _alerts[title + message];

			//Convert the placeholders
			var target = {subject : $system.text.format(language[title], format_title), message : $system.text.format(language[message], format_message).replace(/%index%/, pane), window : pane};
			var body = $system.text.format($system.info.template.alert, target);

			//Create the alert window - TODO Do not define color in script : maybe put the info in style.js
			$system.window.create(pane, language[title], body, '333333', '777777', 'ddcc55', '555555', false, undefined, undefined, 200, undefined, true, false, undefined, undefined, undefined, true);

			if($system.is.digit(timer) && timer > 0) //Fade the alert away
				setTimeout($system.app.method($system.window.fade, [pane, true, null, true]), timer * 1000);

			return pane; //Return the window ID
		}

		this.check = function(request) //Check the return status of the request and report on error
		{
			var code = Number($system.dom.status(request.xml)); //Return code
			if(!$system.is.digit(code, true)) return false;

			switch(code)
			{
				case -1 : var message = {title : 'session/error', content : 'session/explain'}; break; //Session error

				default : return code; break;
			}

			$system.gui.alert($id, message.title, message.content); //Show alert
			return code;
		}

		this.clear = function(node, event) //Function to remove tip on scroll (And fix IE6 bug having selection box corrupted on scroll)
		{
			if($system.tip.on) $system.tip.clear(); //If tip is on, remove it on scroll
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

		/*this.remove = function() //Remove the context menu TODO - Not implemented yet
		{
			if(!$system.node.id(_menu)) return true;
			if(!$system.node.hidden(_menu)) $system.window.fade(_menu);
		}*/
	}

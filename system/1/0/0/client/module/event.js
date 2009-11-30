
	$self.event = new function() //Event related class
	{
		var _class = $id + '.event';

		this.add = function(element, action, execute, capture) //Add an event to an object, in cross engine fashion
		{
			var log = $system.log.init(_class + '.add');
			element = $system.node.target(element); //Get the target object

			if(!$system.is.object(element) || !action.match(/^on[a-z]+$/i) || typeof execute != 'function') return log.param();

			//Add the event to an object
			if(element.addEventListener) return element.addEventListener(action.replace(/^on/i, ''), execute, !!capture);
			else return element.attachEvent(action, execute);
		}

		this.cancel = function(node, event) //Prevent actions to be triggered on elements behind
		{
			if(!$system.is.element(node)) return false;

			if(!event) event = window.event;
			if(!event) return false;

			if(event.type != 'mousedown') return event.cancelBubble = true; //If not a mouse down event, quit here
			var cancel = true; //Do not try to let the window move around when dragging scrollbars

			var style = $system.node.style(node); //The current computed style of the node

			if(style.overflow == 'auto' || style.overflow == 'scroll') //Only cancel the event (window dragging) if the node has scrollbar setting on
			{
				//On buggy engines with wrong scrollWidth or scrollHeight calculation, try to scroll to see if scrollbars exist
				if($system.browser.engine == 'presto' || $system.browser.engine == 'trident' && $system.browser.version < 8)
				{
					var current = node.scrollLeft; //Check if a horizontal scrollbar exists
					node.scrollLeft -= 1;

					if(node.scrollLeft != current) node.scrollLeft = current;
					else
					{
						node.scrollLeft += 1;

						if(node.scrollLeft != current) node.scrollLeft = current;
						else
						{
							var current = node.scrollTop; //Check if a vertical scrollbar exists
							node.scrollTop -= 1;

							if(node.scrollTop != current) node.scrollTop = current;
							else
							{
								node.scrollTop += 1;

								if(node.scrollTop != current) node.scrollTop = current;
								else cancel = false; //If it does not scroll in any way, let the event (motion.start) to trigger
							}
						}
					}
				}
				else //Otherwise, check the values on their existence
				{
					//Konqueror can scroll an empty node with scrollTop/Left even if there is no scrollbar
					//and reports a pixel different on the scroll/clientWidth/Height when no scrollbars are seen
					if($system.browser.engine == 'khtml')
					{
						if(node.scrollWidth == node.clientWidth + 1 && node.scrollHeight == node.clientHeight + 1) cancel = false;
					}
					else if(node.scrollWidth == node.clientWidth && node.scrollHeight == node.clientHeight) cancel = false;
				}
			}

			if(!cancel) return; //If the scrollbars are not present, let the window get dragged by bubbling up to motion.start
			event.cancelBubble = true; //Cancel the actions behind

			while(node.parentNode != document.body) //Find the window object to raise it, since motion.start no longer gets triggered
			{
				node = node.parentNode;
				if(node.nodeType == 1 && node.id && $system.window.list[node.id]) $system.window.raise(node.id); //NOTE : Don't bother to 'break' since next parent node should be 'body'
			}
		}

		this.position = function(event) //Return the position of a node relative to the window
		{
			if(!event) return; //Not logging since this could be triggered very often
			if(event.pageX !== undefined) return {x : event.pageX, y : event.pageY}; //If pageX/Y is available, use them

			var scroll = $system.browser.scroll(); //Get scroll amount
			return {x : event.clientX + scroll.x, y : event.clientY + scroll.y}; //Add the scroll amount to the position
		}

		this.notice = function(id, message, action, remove) //Make or remove an alert icon on the application window's toolbar
		{
			var log = $system.log.init(_class + '.notice');
			if(!$system.is.text(id) || !$system.window.list[id] || !$system.is.text(message)) return log.param();

			var node = $system.node.id($system.info.id + '_' + id + '_notice');

			if(remove !== true)
			{
				var run = function(node, action)
				{
					$system.node.hide(node, true); //Let go of the notice icon
					if(typeof action == 'function') action(); //Run the associated action
				}

				node.onclick = $system.app.method(run, [node, action]); //Set a callback function
				$system.tip.set(node, $system.info.id, 'blank', [message]); //Set a message
			}

			$system.node.hide(node, !!remove);
		}

		this.remove = function(element, action, execute, capture) //Remove an event from an object, in cross engine fashion
		{
			var log = $system.log.init(_class + '.remove');
			if(!$system.is.object(element) || !action.match(/^on[a-z]+$/i) || typeof execute != 'function') return log.param();

			//Remove the event from an object
			if(element.removeEventListener) return element.removeEventListener(action.replace(/^on/i, ''), execute, !!capture);
			else return element.detachEvent(action, execute);
		}

		this.run = function(id, message, action) //Make an alert icon on the application window's toolbar
		{
			var log = $system.log.init(_class + '.notice');
			if(!$system.is.text(id) || !$system.window.list[id] || !$system.is.text(message)) return log.param();
		}

		this.source = function(param) //Get the event object
		{
			if(window.event) return window.event;
			if($system.browser.engine == 'trident') return null; //Avoid getting error at "Event" instance check

			var test = $system.is.object(param) && typeof param.length == 'number' && param[param.length - 1] instanceof Event;
			return test ? param[param.length - 1] : null;
		}

		this.target = function(event) { return event && (event.srcElement || event.target); } //Gets the element that triggered the action
	}

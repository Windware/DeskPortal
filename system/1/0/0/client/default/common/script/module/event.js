
	$self.event = new function() //Event related class
	{
		var _class = $id + '.event';

		this.add = function(element, action, execute, capture) //Add an event to an object, in cross engine fashion
		{
			var log = $system.log.init(_class + '.add');
			element = $system.node.target(element); //Get the target object

			if(!$system.is.object(element) || !action.match(/^on[a-z]+$/i) || typeof execute != 'function') return log.param();

			if($system.browser.os === 'iphone') //For touch device, replace the actions to the equivalents
			{
				switch(action)
				{
					case 'onmousedown' : action = 'ontouchstart'; break;

					case 'onmouseup' : action = 'ontouchend'; break;

					case 'onmousemove' : action = 'ontouchmove'; break;
				}
			}

			//Add the event to an object
			if(element.addEventListener) return element.addEventListener(action.replace(/^on/i, ''), execute, !!capture);
			else return element.attachEvent(action, execute);
		}

		this.cancel = function(node, event) //Prevent actions to be triggered on elements behind
		{
			if(!$system.is.element(node)) return false;

			if(!event) event = window.event;
			if(!event) return false;

			if(event.type !== 'mousedown') return event.cancelBubble = true; //If not a mouse down event, quit here
			var cancel = true; //Do not try to let the window move around when dragging scrollbars

			var style = $system.node.style(node); //The current computed style of the node

			if(style.overflow === 'auto' || style.overflow === 'scroll') //Only cancel the event (window dragging) if the node has scrollbar setting on
			{
				//On buggy engines with wrong scrollWidth or scrollHeight calculation, try to scroll to see if scrollbars exist
				if($system.browser.engine === 'presto' || $system.browser.engine === 'trident' && $system.browser.version < 8)
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
				//Otherwise, check the values on their existence
				else if(node.scrollWidth == node.clientWidth && node.scrollHeight == node.clientHeight) cancel = false;
			}

			if(!cancel) return; //If the scrollbars are not present, let the window get dragged by bubbling up to motion.start
			event.cancelBubble = true; //Cancel the actions behind

			while(node.parentNode != document.body) //Find the window object to raise it, since motion.start no longer gets triggered
			{
				node = node.parentNode;
				if(node.nodeType === 1 && node.id && $system.window.list[node.id]) $system.window.raise(node.id); //NOTE : Don't bother to 'break' since next parent node should be 'body'
			}
		}

		this.position = function(event) //Return the position of a node relative to the window
		{
			if(!event) return; //Not logging since this could be triggered very often
			if(event.pageX !== undefined) return {x : event.pageX, y : event.pageY}; //If pageX/Y is available, use them

			var scroll = $system.browser.scroll(); //Get scroll amount
			return {x : event.clientX + scroll.x, y : event.clientY + scroll.y}; //Add the scroll amount to the position
		}

		this.remove = function(element, action, execute, capture) //Remove an event from an object, in cross engine fashion
		{
			var log = $system.log.init(_class + '.remove');
			if(!$system.is.object(element) || !action.match(/^on[a-z]+$/i) || typeof execute != 'function') return log.param();

			if($system.browser.os === 'iphone') //For touch device, replace the actions to the equivalents
			{
				switch(action)
				{
					case 'onmousedown' : action = 'ontouchstart'; break;

					case 'onmouseup' : action = 'ontouchend'; break;

					case 'onmousemove' : action = 'ontouchmove'; break;
				}
			}

			//Remove the event from an object
			if(element.removeEventListener) return element.removeEventListener(action.replace(/^on/i, ''), execute, !!capture);
			else return element.detachEvent(action, execute);
		}

		this.source = function(param) //Get the event object
		{
			if(window.event) return window.event;
			if($system.browser.engine === 'trident') return null; //Avoid getting error at "Event" instance check

			var test = $system.is.object(param) && typeof param.length === 'number' && param[param.length - 1] instanceof Event;
			return test ? param[param.length - 1] : null;
		}

		this.target = function(event) { return event && (event.srcElement || event.target); } //Gets the element that triggered the action
	}

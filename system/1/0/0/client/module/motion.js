
	$self.motion = new function() //Window dragging class
	{
		//NOTE : This version of the class is never intended to move/resize multiple windows at once
		var _class = $id + '.motion';

		var _box; //Box mover object size

		var _design = {}; //Box background image design URL parameters

		var _lock; //Lock to avoid the 'onmousemove' from getting triggered as fast as possible

		var _mover; //An object for keeping the movement parameters

		var _static = ['A', 'IMG', 'INPUT', 'LABEL', 'SELECT', 'OPTION', 'TEXTAREA']; //Nodes that should not trigger dragging

		var _type = {self : 0, clone : 1, box : 2}; //Motion type definitions

		var _build = function(width, height, x, y, resize) //Build a temporary object to indicate the window movement
		{
			var element = document.createElement('div'); //Create the movement indicator element
			element.id = $id + '_mover'; //Give an unique ID
 
			if(_mover.type == _type.box) //Give a graphic for the box
			{
				$system.image.background(element, _design[resize ? 'resize' : 'move']);
				element.className = $id + '_mover_box';
			}
			else element.className = $id + '_mover_clone'; //TODO - For clone type, give a bright border as well for better visiblity

			//Set its position
			element.style.left = x + 'px';
			element.style.top = y + 'px';

			//Set its size
			element.style.width = width + 'px';
			element.style.height = height + 'px';

			document.body.appendChild(element); //Create it inside the HTML body
			return element; //Return the movement indicator object
		}

		var _offset = function(node) { return {x : node.offsetLeft, y : node.offsetTop}; } //Returns the node's position

		var _size = function(node) //Returns the node's size
		{
			//NOTE & FIXME : These engines have a bad implementation of height inheritance calculation
			//Since clientHeight is bigger from the value set by style.height if a node has multiple rows of a table,
			//and any of it having bigger actual size than specified size (ex : due to having the content size bigger than table specified row size)
			//will push the size of the table bigger than the value specified making resizing look very odd.
			//
			//Using the style.height will fix the problem, at the expense of making the cursor get off the place while resizing,
			//which is the least hazardous workaround and is the method used now.
			//
			//Removing the size style off "tbody" on app body template will fix it
			//but will render the "overflow" styles useless on inner nodes as no height will inherit any longer
			//for it to determine when it is over flown, which is usually not acceptable.
			//
			//Also, setting the size statically on the inner nodes where overflow should be placed will fix it,
			//at the expense of losing auto resizable capability.
			//
			//On the other hand, clientWidth will report the same size set by "style.width".
			//
			//The reason for using clientHeight instead of style.height is that a window may no longer shrink
			//due to inner nodes being at its smallest possible size, thus relying on style.height can be inaccurate
			//while clientHeight should report the actual size being displayed.
			//FIXME - Above explanation doesn't make good sense on first time readers
			var bad = $system.browser.engine == 'gecko' || $system.browser.engine == 'presto' || $system.browser.engine == 'khtml' || $system.browser.engine == 'trident' && $system.browser.version >= 7;
			return {x : node.clientWidth, y : bad ? parseInt(node.style.height) : node.clientHeight};
		}

		this.init = function() //Initialize the move box object's background image
		{
			_box = $system.window.edge * 4; //Box size
			var color = {move : '000000', resize : 'ffeeaa'}; //Colors for move box object when moving and resizing

			for(var type in color)
			{
				var params = '%%server/php/front.php?task=motion.init&color=333333&background=%%&border=333333&round=%%&shadow=%%&place=circle&edge=%%';
				var variables = [$system.info.root, color[type], $global.user.pref.round == 1 ? 1 : 0, $system.window.shadow, _box / 2];

				_design[type] = $system.text.format(params, variables);
				$system.image.set(document.createElement('img'), _design[type]); //Preload the box background image
			}
		}

		this.move = function(event) //Moves the window by mouse dragging (Have as less computation as possible)
		{
			if(_lock) return true; //If throttled, do not execute anything

			_lock = true; //Lock for a short while
			setTimeout(function() { _lock = false; }, $system.gui.interval); //Give a lock to throttle mouse move event from triggering as often as possible

			if(__tip.timer) //If a tip is given timer for display, track mouse position
			{
				if($system.is.digit(event.pageX)) __tip.position = {x : event.pageX, y : event.pageY}; //If pageX/Y is available, use them
				else __tip.position = {x : event.clientX + document.body.scrollLeft || document.documentElement.scrollLeft, y : event.clientY + document.body.scrollTop || document.documentElement.scrollTop};
			}

			if(!_mover) return true;

			if($system.browser.os == 'iphone')
			{
				event.preventDefault(); //Avoid scrolling the screen

				if(event.touches.length != 1) return true; //Only when touched by 1 finger
				event = event.touches[0]; //Get the touch event
			}
			else
			{
				//Find the mouse event that triggered
				if(!event) event = window.event; //NOTE : Not using 'event.source' for a possible performance reason

				//If mouse is not held, quit moving it (Happens when the cursor goes out of browser space before the button goes up) (khtml reports 65535 on event.button)
				if($system.browser.engine != 'khtml' && event.button != $system.browser.click.left) return $system.motion.stop();
			}

			var current = $system.event.position(event); //Get the cursor position
			_mover.diff = {x : current.x - _mover.position.x, y : current.y - _mover.position.y}; //Amount moved from the start

			if(_mover.resize && _mover.type != _type.box) //If resizing and the movement is not box type
			{
				var size = {x : _mover.size.x + _mover.diff.x, y : _mover.size.y + _mover.diff.y}; 
				var target = (_mover.type == _type.self) ? _mover.body : _mover.object; //Target the window body itself

				//Set the size of the target accordingly
				if(size.x > 0) target.style.width = size.x + 'px';
				if(size.y > 0) target.style.height = size.y + 'px';
			}
			else //If moving, change the position of the mover
			{
				_mover.object.style.left = _mover.start.x + _mover.diff.x + 'px';
				_mover.object.style.top = _mover.start.y + _mover.diff.y + 'px';
			}

			return true;
		}

		this.start = function(id, resize) //Prepare for the object's motion
		{
			if(_mover) return true; //If the previous movement hasn't finished yet, do not start a new move

			var node = $system.node.id(id); //The window object that initiated the move
			if(!node) return true; //If the object is not available, quit but return 'true' to make sure the click event completes

			var event = $system.event.source(arguments); //Find the mouse event that triggered
			if(!event) return true;

			var source = $system.event.target(event); //Find what element was clicked on

			if($system.browser.os == 'iphone')
			{
				if(event.touches.length != 1) return true; //Only when touched by 1 finger
				while(source.nodeType != 1) source = source.parentNode; //Keep moving up till a tag element is found as text nodes will be recognized as the event source as well (Ultimately 'body' will stop the loop)
			}
			else if(event.button != $system.browser.click.left) return true; //If not clicked with a left button, quit

			$system.browser.deselect(); //Let go of selected text if any
			$system.tip.clear(); //Remove any tips displayed

			var depth = $system.window.raise(id); //Bring the clicked pane to the front most

			//If clicked on any of the specified elements, do not drag the window to avoid crippled user interface
			//TODO - Avoid images from getting dragged around in certain browsers by capturing 'onmousedown' on IMG elements (Setting it here is too late)
			if($system.array.find(_static, source.nodeName)) return true;
			var target = $system.window.list[id]; //The target window object

			if(!target || !resize && target.locked) return true; //If the pane is locked for moving, quit
			if(resize && !target.visible('body')) return true; //When resizing, do not allow resizing if body is hidden

			if($system.browser.os == 'iphone')
			{
				event.preventDefault();
				event = event.touches[0]; //Get the touch event
			}

			if($system.browser.engine != 'trident' || $system.browser.version >= 7) $system.node.opacity(id, 50); //Set translucency when moving

			_mover = //Keep the movement parameters
			{
				body : $system.window.list[id].body, //The body cell of the window

				depth : depth, //Its current z index value

				node : node, //The window itself

				resize : resize, //Whether this is resizing or moving

				position : $system.event.position(event), //Initially clicked position

				type : $system.browser.os == 'iphone' ? _type.box : Number($global.user.pref.move) //Movement type (For performance reason, force box type for iPhone)
			};

			switch(_mover.type)
			{
				case _type.self : //If move mode is self movement, set the mover as itself
					_mover.object = node;

					if(resize) _mover.size = _size(_mover.body); //Initial window size
					else _mover.start = _offset(node); //Initial position
				break;

				case _type.clone : //If move mode is clone creation
					//Create the mover (Subtract the size of border on both ends)
					_mover.object = _build(node.clientWidth - 2, node.clientHeight - 2, node.offsetLeft || 0, node.offsetTop || 0);

					if(resize) _mover.size = {x : _mover.object.clientWidth, y : _mover.object.clientHeight}; //Cloned object size
					else _mover.start = _offset(node); //Initial position

					$system.window.raise(_mover.object.id, undefined, true);
				break;

				case _type.box : //If move mode is box type, create the small box : TODO - Change color or appearance of the mover for move/resize
					_mover.object = _build(_box, _box, _mover.position.x - _box / 2, _mover.position.y - _box / 2, resize);

					if(resize) _mover.size = _size(_mover.body); //Initial window size
					_mover.start = _offset(_mover.object); //Initial position (Required for both move/resize)

					$system.window.raise(_mover.object.id, undefined, true);
				break;

				default :
					_mover = undefined;
					return true; //Make sure the onmousedown event succeeds (IE disables text selecting if 'false' is returned)
				break;
			}

			if(_mover.type == _type.box) ; //Bring the mover to the top
			else if(_mover.type == _type.box) $system.window.raise(_mover.object.id, undefined, true); //Bring the mover to the top

			$self.gui.select(false); //Disable text selection
			return true; //Required
		}

		this.stop = function() //Stop any windows from moving
		{
			$self.gui.select(true); //Enable text selection

			if($system.browser.os == 'iphone')
			{
				var event = $system.event.source(arguments);
				event.preventDefault();
			}

			if(!_mover || !$system.node.id(_mover.object.id)) return true; //If nothing is moving, quit

			if($system.browser.engine == 'trident' && $system.browser.version >= 7) _mover.node.style.removeAttribute('filter');
			if($system.browser.engine != 'trident' || $system.browser.version >= 7) $system.node.opacity(_mover.node.id, 100); //Set back to normal opacity

			if(_mover.diff && $system.is.element(_mover.node)) //If moving or resizing took place and the original object still exists
			{
				if(_mover.resize) //If resizing
				{
					switch(_mover.type)
					{
						case _type.self : //Take the current size
							var finish = _size(_mover.body);
						break;

						case _type.clone : case _type.box : //Calculate the final size by the amount moved
							var size = _size(_mover.body);
							var finish = {x : size.x + _mover.diff.x, y : size.y + _mover.diff.y};
						break;
					}

					//Keep the values positive
					if(finish.x < 1) finish.x = 1;
					if(finish.y < 1) finish.y = 1;

					//Change the size of the source object
					_mover.body.style.width = finish.x + 'px';
					_mover.body.style.height = finish.y + 'px';

					var parameter = {width : finish.x, height : finish.y}; //Parameter to save
				}
				else //If moving
				{
					switch(_mover.type)
					{
						case _type.self : case _type.clone : //If not box type, use the object's position
							var finish = {x : parseInt(_mover.object.style.left), y : parseInt(_mover.object.style.top)};
						break;

						case _type.box :
							//The window's final position
							var finish = {x : _mover.node.offsetLeft + _mover.diff.x, y : _mover.node.offsetTop + _mover.diff.y};
						break;
					}

					//Set the window's position
					_mover.node.style.left = finish.x + 'px';
					_mover.node.style.top = finish.y + 'px';

					var parameter = {left : finish.x, top : finish.y}; //Parameter to save
				}

				if($system.is.md5($global.user.ticket)) //If logged in
				{
					if(_mover.depth !== false) parameter.depth = _mover.depth; //Add depth if it changed
					var save; //Whether to make a remote request to save the values

					for(var index in parameter) save = true; //If it has a member, save it
					if(save) $system.window.save(_mover.node.id, parameter); //Save the window information
				}
			}

			if(_mover.type != _type.self) $system.node.remove(_mover.object.id); //Remove the mover object
			_mover = undefined; //Remove the mover object

			return true; //Return true to complete the 'onmouseup' event properly
		}
	}

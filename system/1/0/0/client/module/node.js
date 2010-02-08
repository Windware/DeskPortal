
	$self.node = new function() //Node manipulation class
	{
		var _class = $id + '.node';

		var _speed = 10; //Amount of difference each fading makes (Bigger the less cpu use but less smooth)

		var _level = 5; //Fade stepping in 10th of a second out of 100

		var _display = function(id) //Fade process that is called repeatedly (Make it as less computational as possible)
		{
			var target = __node.fading[id]; //Keep the fade state
			if(!target) return false;

			target.level += target.way; //Level of opacity at this cycle

			if(target.level >= 100 || target.level <= 0) //If the fade process is finished
			{
				if(target.level <= 0) //When it becomes transparent
				{
					$system.node.opacity(target.node, 0); //Set its style opacity to zero

					if(target.destroy) $system.node.remove(target.node); //If it's set to destroy the node, do so
					else $system.node.hide(id, true); //If not, set a hidden class attribute
				}
				else $system.node.opacity(target.node, 100); //Otherwise, set to fully opaque

				if($global.user.pref.fade) clearInterval(__node.fading[id].timer); //Let go of the interval timer
				if($system.browser.engine == 'trident') target.node.style.removeAttribute('filter');

				//Remove the 'filter' attribute completely to allow anti aliasing again on the node
				if(typeof target.execute == 'function') target.execute(); //If a function was set, execute it

				delete __node.fading[id]; //Let go of the state object
			}
			else $system.node.opacity(target.node, target.level); //Set the opacity to the current level

			return true;
		}

		this.classes = function(node, name, set) //Set a class attribute to a node safely
		{
			var log = $system.log.init(_class + '.classes');
			var target = $system.node.target(node);

			if(!$system.is.text(name)) return log.param();
			if(!target) return false;

			var current = RegExp('(^| +)' + $system.text.regexp(name) + '( +|$)'); //Regular expression to remove the class
			if(set === undefined) return target.className.match(current) !== null; //If only checking for the class' existence

			if(!set) return target.className = target.className.replace(current, ' '); //Strip the class
			if(!target.className.match(current)) return target.className += ' ' + name; //Append the class if not already declared
		}

		this.fade = function(id, direction, execute, destroy, quick) //Fades a node in and out depending on user configuration
		{
			var log = $system.log.init(_class + '.fade');
			if(!$system.is.text(id)) return log.param();

			var node = $system.node.id(id); //The node element
			if(!node) return log.dev($global.log.info, 'dev/fade', 'dev/exist', [id]);

			if(__node.fading[id]) //If currently in the fade process
			{
				__node.fading[id].level = __node.fading[id].way > 0 ? 100 : 0; //Set fade level to the finished state
				_display(id); //Call the fade processor
			}

			if(direction === undefined) //If direction is not defined, try to flip the current visibility
			{
				var style = $system.node.style(id); //The computed style object
				if(typeof style != 'object') return false;

				direction = style.display != 'none';
			}

			if(direction) //Set the fade speed according to direction
			{
				var fade = 100;
				var way = -_speed;
			}
			else
			{
				var fade = 0;
				var way = _speed;

				$system.node.hide(id, false); //Remove the element class for hiding
			}

			__node.fading[id] = {node : node, destroy : destroy, execute : execute} //Keep the state of the parameters

			if(quick !== true && $global.user.pref.fade) //Set fade process parameter
			{
				__node.fading[id].level = fade;
				__node.fading[id].way = way;

				var duration = _level * _speed;
				__node.fading[id].timer = setInterval($system.app.method(_display, [id]), duration); //Do gradual opacity

				_display(id); //Do the initial step before the interval arrives
			}
			else //If fading is disabled, set it to instantly stop fading
			{
				__node.fading[id].level = 100 - fade;
				__node.fading[id].way = 0;

				_display(id);
			}
		}

		this.hidden = function(node) { return $system.node.classes(node, $id + '_hidden'); } //Finds if a node is hidden or not

		this.hide = function(node, mode, destroy) //Hides or unhides a node : TODO - Apply animation effect
		{
			var node = $system.node.target(node);
			if(!$system.is.element(node)) return false;

			if(mode === undefined) mode = !$system.node.hidden(node);
			$system.node.classes(node, $id + '_hidden', mode); //Set the hiding class on or off

			if(mode && destroy) $system.node.remove(node);
		}

		this.hover = function(node, style) //Change style on hover
		{
			var log = $system.log.init(_class + '.hover');
			if(!$system.is.text(style)) return log.param();

			var node = $system.node.target(node);
			if(!$system.is.element(node)) return false;

			//Make hovered style compatibile for older IE
			$system.event.add(node, 'onmouseover', $system.app.method($system.node.classes, [node, style, true]));
			$system.event.add(node, 'onmouseout', $system.app.method($system.node.classes, [node, style, false]));
		}

		//Returns the node by referring to its ID
		this.id = function(name) { return $system.is.text(name) ? document.getElementById(name) : null; }

		this.opacity = function(node, level) //Sets a node's opacity
		{
			var node = $system.node.target(node);
			if(!$system.is.element(node) || !$system.is.digit(level)) return;

			if(node.style.opacity !== undefined) node.style.opacity = level / 100;
			//TODO - For IE8, it is said that it needs to use DX filter (because?)
			else if(node.style.filter !== undefined) node.style.filter = 'alpha(opacity=' + level + ')';
		}

		this.remove = function(node) //Remove a node
		{
			var log = $system.log.init(_class + '.remove');

			var target = $system.node.target(node);
			if(!target) return log.dev($global.log.info, 'dev/remove', 'dev/exist');

			try { if(target.parentNode.removeChild(target)) return true; } //Remove the node from the parent object

			catch(error) { return log.dev($global.log.error, 'dev/remove/fail', 'dev/check', [$system.browser.report(error)]); }
		}

		this.style = function(node, key) //Get the computed style object
		{
			var log = $system.log.init(_class + '.style');

			node = $system.node.target(node);
			if(!$system.is.element(node)) return false;

			//For engines with 'defaultView'
			if(document.defaultView && document.defaultView.getComputedStyle) var style = document.defaultView.getComputedStyle(node, null);
			else if(node.currentStyle) var style = node.currentStyle; //For engines with 'currentStyle' (ex : IE)
			else return log.dev($global.log.error, 'dev/style/retrieve', 'dev/style/retrieve/solution', [id]);

			return $system.is.text(key) ? style[key] : style;
		}

		this.target = function(subject) //Finds the node by either the node reference itself or the name of its ID
		{
			switch(typeof subject)
			{
				case 'object' : return subject; break; //If an object is passed, return as is

				case 'string' : return $system.node.id(subject); break; //If a string is passed, assume it's a DOM id

				default : return null; break; //Otherwise, return a null object
			}
		}

		this.text = function(node, text) //Sets a text node on an element
		{
			var log = $system.log.init(_class + '.set');
			var node = $system.node.target(node);

			if(!$system.is.element(node) || !$system.is.text(text, true) && !$system.is.digit(text)) return log.param();

			if(typeof node.textContent == 'string') node.textContent = text; //Non IE
			else if(typeof node.innerText == 'string') node.innerText = text; //IE
			else return false;

			return true;
		}
	}

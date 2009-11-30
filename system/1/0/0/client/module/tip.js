
	$self.tip = new function() //Tip related class
	{
		var _class = $id + '.tip';

		var _timer; //Tip display delay timer

		var _node = $id + '_tip'; //Name of the tip node

		var _margin = 5; //Amount of pixel from the event coordinate to put the tip at

		var _display = function(log, id, section, x, y, format) //Create the actual tip and append it onto HTML body
		{
			var clue = $system.language.strings(id, 'tip.xml'); //Get the tip string template
			if(!$system.is.text(clue[section])) return log.dev($global.log.error, 'dev/tip/empty', 'dev/tip/empty/solution', [id, section]);

			$system.tip.clear(); //Remove any remaining tip

			//Create the tip element and apply properties
			var tip = document.createElement('div');
			tip.id = _node; //TODO - Need multiple slots for newer tips to be displayed while the old one fades out

			//Set its position
			if(typeof x == 'number') tip.style.left = x + 'px';
			if(typeof y == 'number') tip.style.top = y + 'px';

			//Format the tip if values are specified and put the content inside the node
			tip.innerHTML = $system.text.format(clue[section], format);

			document.body.appendChild(tip); //Apply to the body : TODO - Do fading
			tip.style.zIndex = $system.window.depth + 1; //Raise the tip to the front most

			$system.tip.on = true; //Indicate "motion.js" that tip exists
		}

		this.on = false; //Flag to determine whether tip is on or off quickly in 'motion.js' and 'gui.js'

		this.clear = function() //Remove an already displayed or pending tip
		{
			if(_timer) _timer = clearTimeout(_timer); //If timer is alive, clear it

			if($system.node.id(_node)) $system.node.remove(_node); //Remove the tip node
			$system.tip.on = false; //Set that it's off
		}

		//Returns HTML portion to create a tip on an element ('phrase' is required since this function will be used as a callback of String.replace)
		this.link = function(id, phrase, match, param)
		{
			var tip = ' onmouseover="%top%.%system%.tip.make(\'%id%\', \'%tip%\', %param%, event)" onmouseout="%top%.%system%.tip.clear()"';
			param = $system.is.array(param) ? "['" + param.join("', '") + "']" : 'null';

			return $system.text.template(tip, id).replace(/%tip%/, match).replace(/%param%/, param);
		}

		this.make = function(id, section, format) //Creates a tip on mouseover after a little delay
		{
			var log = $system.log.init(_class + '.make');
			var event = $system.event.source(arguments);

			if(!event) return log.param();

			event.cancelBubble = true; //Do not let any other elements behind it to trigger the tip as well
			if(!$system.is.path($system.app.path(id)) || !$system.is.text(section)) return log.param();

			var scroll = $system.browser.scroll(); //Get amount of scrolling done

			//Set the position where to display the tip at
			var offset = {x : event.clientX + scroll.x + _margin, y : event.clientY + scroll.y + _margin};
			if(_timer) clearTimeout(_timer); //If timer is alive, clear it

			//Create the function to get the tip displayed
			var appearance = $system.app.method(_display, [log, id, section, offset.x, offset.y, format]);
			_timer = setTimeout(appearance, $global.user.pref.delay * 1000); //Set a timer event to display the tip
		}

		this.remove = function(node) //Removes the tip off a node
		{ //FIXME : This overrides previous onmouseover/out
			var log = $system.log.init(_class + '.remove');
			node = $system.node.target(node); //Get the target element

			if(!$system.is.element(node)) return log.param();
			node.onmouseover = node.onmouseout = '';
		}

		this.set = function(node, id, tip, format, newline) //Shortcut to set a tip on a node
		{ //FIXME : This overrides previous onmouseover/out
			node = $system.node.target(node); //Get the target element

			if(newline) for(var index in format) //Make new lines on the values if specified
				if($system.is.text(format[index])) format[index] = format[index].replace(/\n/g, '<br />\n');

			if(!$system.is.text(tip) || !$system.is.id(id) || !$system.is.type(format, 'array') || !$system.is.element(node)) return log.param();

			node.onmouseover = $system.app.method($system.tip.make, [id, tip, format]); //Set the new tip content
			node.onmouseout = $system.tip.clear;
		}
	}

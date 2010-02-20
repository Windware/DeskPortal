
	$self.tip = new function() //Tip related class
	{
		var _class = $id + '.tip';

		var _count = 0; //Tip ID counter

		var _background = {color : 'ffeeaa', opacity : 85}; //Tip background color and opacity

		var _design = $system.text.format('%%server/php/front.php?task=tip.make&color=333333&background=%%&border=333333&edge=1&shadow=1&round=1&place=cm&through=%%', [$system.info.root, _background.color, _background.opacity]);

		var _displayed = []; //List of displayed tips

		var _node = $id + '_tip_'; //Name of the tip node

		var _margin = 5; //Amount of pixel from the event coordinate to put the tip at

		this.init = function() { $system.image.set(document.createElement('img'), _design); } //Preload the tip background

		this.clear = function() //Remove an already displayed or pending tip
		{
			if(__tip.timer) __tip.timer = clearTimeout(__tip.timer); //If timer is alive, clear it

			for(var i = 0; i < _displayed.length; i++) //Remove any tips displayed
			{
				var id = _node + _displayed[i]; //Tip object ID
				if(!$system.node.id(id)) continue;

				delete __node.fading[id]; //Force allow fading out even while fading in by removing the fade object
				$system.node.fade(id, true, null, true); //Remove the tip
			}

			_displayed = []; //Clear out the list of displayed tips
			delete __tip.on; //Set that it's off for 'gui.js'
		}

		//Returns HTML portion to create a tip on an element ('phrase' is required since this function will be used as a callback of String.replace)
		this.link = function(id, phrase, match, param, line)
		{
			var tip = ' onmouseover="%top%.%system%.tip.make(\'%id%\', \'%tip%\', %param%, ' + (line === true ? 'true' : 'false') + ', event)" onmouseout="%top%.%system%.tip.clear()"';
			
			if($system.is.array(param))
			{
				for(var i = 0; i < param.length; i++) param[i] = $system.text.escape(param[i]);
				param = "['" + param.join("', '") + "']";
			}
			else param = 'null';

			return $system.text.template(tip, id).replace(/%tip%/, $system.text.escape(match)).replace(/%param%/, param);
		}

		this.make = function(id, section, format, line) //Creates a tip on mouseover after a little delay
		{
			var log = $system.log.init(_class + '.make');
			var event = $system.event.source(arguments);

			if(!event) return log.param();
			event.cancelBubble = true; //Do not let any other elements behind it to trigger the tip as well

			if(!$system.is.path($system.app.path(id)) || !$system.is.text(section)) return log.param();
			if(__tip.timer) clearTimeout(__tip.timer); //If timer is alive, clear it

			var display = function(id, section, format, line) //Create the actual tip and append it onto HTML body
			{
				var log = $system.log.init(_class + '.make.display');
				var clue = $system.language.strings(id, 'tip.xml'); //Get the tip string template

				if(!$system.is.text(clue[section])) return log.dev($global.log.error, 'dev/tip/empty', 'dev/tip/empty/solution', [id, section]);
				$system.tip.clear(); //Remove any remaining tip

	 			if(!__tip.position || !$system.is.digit(__tip.position.x) || !$system.is.digit(__tip.position.y)) return false; //If mouse position isn't tracked, quit

				var tip = document.createElement('div'); //Create the tip element and apply properties

				tip.id = _node + (++_count);
				tip.className = $id + '_helper';

				//Set its position
				tip.style.left = __tip.position.x + 5 + 'px';
				tip.style.top = __tip.position.y + 5 + 'px';
				tip.style.zIndex = $system.window.depth + 1; //Raise the tip to the front most

				var text = $system.text.format(clue[section], format);
				if(line === true) text = text.replace(/\n/g, '<br />\n');

				tip.innerHTML = text; //Format the tip if values are specified and put the content inside the node
				document.body.appendChild(tip); //Apply to the body

				$system.node.fade(tip.id, false); //Fade it in
				$system.image.background(tip, _design); //Set its background color image file

				delete __tip.timer; //Stop tracking the mouse position
				delete __tip.position;

				__tip.on = true; //Indicate 'gui.js' that tip exists to be cleared on scrolling
				_displayed.push(_count);
			}

			__tip.timer = setTimeout($system.app.method(display, [id, section, format, line]), $global.user.pref.delay * 1000); //Set a timer event to display the tip
		}

		this.remove = function(node) //Removes the tip off a node
		{ //FIXME : This overrides previous onmouseover/out
			var log = $system.log.init(_class + '.remove');
			node = $system.node.target(node); //Get the target element

			if(!$system.is.element(node)) return log.param();
			node.onmouseover = node.onmouseout = '';
		}

		this.set = function(node, id, tip, format, lines) //Shortcut to set a tip on a node
		{ //FIXME : This overrides previous onmouseover/out
			var log = $system.log.init(_class + '.set');
			node = $system.node.target(node); //Get the target element

			if(lines) for(var index in format) //Make new lines on the values if specified
				if($system.is.text(format[index])) format[index] = format[index].replace(/\n/g, '<br />\n');

			if(!$system.is.text(tip) || !$system.is.id(id) || !$system.is.type(format, 'array') || !$system.is.element(node)) return log.param();

			node.onmouseover = $system.app.method($system.tip.make, [id, tip, format]); //Set the new tip content
			node.onmouseout = $system.tip.clear;
		}
	}

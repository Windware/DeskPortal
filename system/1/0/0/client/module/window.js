
	$self.window = new function() //Window object manipulation class
	{
		var _class = $id + '.window';

		var _spots = $system.array.list('tl tm tr cl cm cr cm bl bm br'); //Window cell position code

		var _window = function(id) //An object to track the window's condition
		{
			this.bar = null; //DOM reference to the toolbar cell

			this.body = null; //DOM reference to the inner body cell

			this.design; //The background color to keep

			this.displayed = {bar : false, body : true}; //State of display for both of the parts

			this.fixed = false; //Indicates if the window should retain its configuration

			this.id = id; //Own HTML ID

			this.locked = false; //If set to 'true', it should not be moved or resized

			this.object = null; //Refer to the actual object within the DOM

			this.title = ''; //Title of the window

			this.visible = function(part) //Return the visibility of various parts of a window
			{
				switch(part)
				{
					case 'body' : case 'bar' : return this[part] && !$system.node.hidden(this[part].id); break;

					default : return this.object && !$system.node.hidden(this.object.id); break;
				}
			}
		}

		this.depth = 0; //Level of window depths

		this.edge = 10; //Size of the edges of a window : TODO - these should stay here?

		this.shadow = 8; //Size of drop shadows

		this.list = {}; //List of windows to be accessed from outside

		this.init = function() //Initialize window templates
		{
			var log = $system.log.init(_class + '.init');
			var request = $system.network.item($system.info.devroot + 'template/*.html', true);

			for(var i = 0; i < request.length; i++) //Process templates
			{
				if(!request[i].valid(true)) //Quit with critical error, if they are unavailable
					return log.dev($global.log.critical, 'dev/window/template/solution', 'dev/window/template/solution', [request[i].file]);

				var name = request[i].file.replace(/\.html$/, '');
				$system.info.template[name] = $system.language.apply($id, request[i].text, undefined, undefined, true);
			}

			delete $system.info.template.index; //Remove the unnecessary cache of the top page template
			if(!$system.info.template.body) return log.dev($global.log.critical, 'dev/window/body', 'dev/window/body/solution');

			//Replace parts of the container with appropriate decorations
			$system.info.template.body = $system.info.template.body.replace(/%edge%/g, $system.window.edge).replace(/%shadow%/g, $system.window.edge + $system.window.shadow);
		}

		this.create = function(id, title, content, color, hover, background, line, toolbar, left, top, width, height, center, up, visible, execute, prepare, fixed, quick)
		{ //TODO - Add hover interface. Make it switchable in user configuration. Put mouse over an action link for configured amount of time (ex : 3sec) and make the action occur
			var log = $system.log.init(_class + '.create');
			if(!$system.is.text(id)) return log.param();

			//If the document body element is not ready or the window node already exists, quit
			if(!document.body) return log.dev($global.log.error, 'dev/window/ready', 'dev/window/ready/solution');
			if($system.node.id(id)) return log.dev($global.log.warning, 'dev/window/exist', 'dev/window/exist/solution', [id], null, true);

			if(!$system.is.object($global.user.window[id])) $global.user.window[id] = {}; //Create the hash if it does not exist
			var settings = $global.user.window[id]; //Application specific configuration

			//Set the colors
			if(settings.color !== undefined) color = settings.color;
			if(settings.hover !== undefined) hover = settings.hover;

			if(settings.window !== undefined) background = settings.window;
			if(settings.line !== undefined) line = settings.line;

			//Give default colors if no good
			if(!$system.is.color(color)) color = '333333';
			if(!$system.is.color(hover)) hover = 'aaaaaa';

			if(!$system.is.color(background)) background = 'ffffff';
			if(!$system.is.color(line)) line = '333333';

			if(visible === undefined) visible = true; //By default, show the built window

			if(!$system.is.text(content)) content = ''; //If no proper value is given, keep it empty
			if(!$system.is.text(title)) title = id; //Use the ID as the title, if no proper title is given

			if($global.top[id]) //If it's an application window, grab the icon automatically
			{
				var request = $system.network.item($global.top[id].info.devroot + 'graphic/icon.png'); //Get the icon request object

				//If application icon exists, make HTML out of it
				var icon = request.valid() ? '<img src="' + $system.image.source(id, 'icon.png') + '" /> ' : '';
				var tools = $system.info.template.tools.replace(/%id%/g, id); //Swap the ID string on the buttons
			}
			else
			{
				var icon = ''; //Place no icons
				var tools = ''; //Don't add report, conf and info buttons to the toolbar
			}

			$system.window.list[id] = new _window(id); //Create the window object to track its condition
			var pane = $system.window.list[id]; //Alias the window object

			pane.title = title; //Set the window title
			if(fixed) pane.fixed = true; //For windows that do not need to remember the configurations

			//Display status of the toolbar
			if(settings.bar == 1) pane.displayed.bar = true;
			else if(settings.bar == 0) pane.displayed.bar = false;
			else pane.displayed.bar = toolbar;

			//Display status of the body
			if(settings.body == 1) pane.displayed.body = true;
			else if(settings.body == 0 && settings.bar != 0) pane.displayed.body = false;
			else pane.displayed.body = true;

			//Replace the template variables
			var body = $system.text.replace($system.info.template.body, ['%tools%', '%content%', /%logo%/g, /%title%/g], [tools, content, icon, title]);

			var box = document.createElement('div'); //Create the application window container
			box.innerHTML = $system.text.template(body, id); //Replace all the necessary variables and attach to the container

			if(!box.firstChild || !box.firstChild.rows) //If for some reason, the template got screwed up
			{
				log.dev($global.log.error, 'dev/window/structure', 'dev/window/structure/solution', [id]);

				var title = $global.app[id] && $global.app[id].title ? $global.app[id].title : id;
				return log.user($global.log.error, 'user/window/create', '', [title]);
			}

			var index = 0; //To count which cell the loop is in

			for(var i = 0; i < box.firstChild.rows.length; i++) //For every row of the window
			{
				var row = box.firstChild.rows.item(i); //The row itself

				for(var j = 0; j < row.cells.length; j++) //For every cell of the row
				{
					var cell = row.cells.item(j); //The cell itself
					var action; //Action to be associated on the cell

					if(++index == 3) //On the top right corner (And keep adding 'index' variable)
					{
						action = $system.app.method($system.tool.hide, [id, 'bar']);
						cell.style.cursor = 'n-resize'; //Give a function to hide the toolbar
					}
					//If at the lower right corner, give a resize function
					else if(index == 10) action = $system.app.method($system.motion.start, [id, true]);
					//For other cells, give regular move function
					else action = $system.app.method($system.motion.start, [id, false]);

					$system.event.add(cell, $system.browser.os == 'iphone' ? 'ontouchstart' : 'onmousedown', action); //Set the event on the cell
				}
			}

			//Set it at top left first to try to avoid scrollbars before the position is determined
			box.firstChild.style.left = '0px';
			box.firstChild.style.top = '0px';

			box.firstChild.style.visibility = 'hidden'; //Keep it hidden first
			document.body.appendChild(box.firstChild); //Append to the document body

			if(!$system.node.id(id)) return log.dev($global.log.error, 'dev/window/build', 'dev/window/build/solution', [id]);

			//If buttons exist, fix the cell that contains the toolbar to a static size of the toolbar to keep that cell from getting resized
			var buttons = $system.node.id($id + '_' + id + '_bar');
			if(buttons) buttons.parentNode.style.height = buttons.clientHeight + 'px';

			pane.object = $system.node.id(id); //Apply the HTML node to its reference variable

			if($system.is.digit(settings.depth)) $system.window.raise(pane.object, settings.depth, undefined, true); //Set the z axis height to the configured value
			else $system.window.raise(id, undefined, undefined, true); //Otherwise bring the window to the foremost

			var replacer = [$system.info.root, color, background, line, $system.window.edge, $system.window.shadow, $global.user.pref.round == "1" ? 1 : 0]; //Set the window design specifier

			//Pre define the pane design request string
			pane.design = $system.text.format('%%server/php/front.php?task=window.create&color=%%&background=%%&border=%%&edge=%%&shadow=%%&round=%%&place=', replacer);

			if(!$global.user.pref.fade || quick === true) //If not fading, set the background statically once
			{
				var rows = $system.node.id(id).rows;
				var index = 0;

				for(var i = 0; i < rows.length; i++) //For every row of the window
				{
					var cells = rows.item(i).cells; //The cells in the row

					for(var j = 0; j < cells.length; j++) //Set the window color
						$system.image.background(cells.item(j), pane.design + _spots[index++]);
				}
			}

			//Apply HTML element to its reference variable
			var section = ['bar', 'body', 'box'];
			for(var i = 0; i < section.length; i++) pane[section[i]] = $system.node.id([$id, id, section[i]].join('_'));

			if(center) pane.body.style.textAlign = 'center'; //Make the layout centered if specified
			if(up) pane.body.style.verticalAlign = 'top'; //Move the content to the top of the window

			//Set the size of the window as specified
			if($system.is.digit(width)) pane.body.style.width = width + 'px';
			if($system.is.digit(height)) pane.body.style.height = height + 'px';

			if(pane.displayed.bar) $system.tool.hide(id, 'bar', true); //Show the bar if set so
			pane.object.style.color =  '#' + color; //Set the string color

			var css = document.createElement('style'); //Create CSS for hover style

			css.id = [$system.info.id, 'style', id, 'hover'].join('_');
			css.type = 'text/css';

			//Create the CSS declaration on hovered objects inside the window
			var style = $system.text.format('table#%% a:hover, table#%% a:active { color : #%%; }', [id, id, hover]);

			if(css.styleSheet) css.styleSheet.cssText = style; //For IE
			else if(css.appendChild) css.appendChild(document.createTextNode(style)); //For others

			$system.dom.tags(document, 'head')[0].appendChild(css); //Put it inside 'head' tag

			if(typeof prepare == 'function')
			{
				try { prepare(); } //Do any preparation sequence before the window goes live

				catch(error)
				{
					log.dev($global.log.error, 'dev/window/prepare', 'dev/window/prepare/solution', [id, $system.browser.report(error)]);
					$system.app.unload(id); //Clean up the partially processed window
				}

				if(!$system.node.id(id)) //If the window got cleared due to application unloading or from some other errors
					return log.dev($global.log.error, 'dev/window/gone', 'dev/window/gone/solution', [id]);
			}

			var size = $system.browser.size(); //Get the browser's window size

			//Set the position of the window
			//Use user specified value if set, else use app specified value or else put it at the middle of the screen
			if(!$system.is.digit(settings.left, true))
			{
				if($system.is.digit(left, true)) settings.left = left;
				else if($system.is.digit(size.x)) settings.left = parseInt((size.x - pane.object.clientWidth) / 2, 10);
			}

			if(!$system.is.digit(settings.top, true))
			{
				if($system.is.digit(top, true)) settings.top = top;
				else if($system.is.digit(size.y)) settings.top = parseInt((size.y - pane.object.clientHeight) / 2, 10);
			}

			//Override settings on the body cell
			if($system.is.digit(settings.width)) pane.body.style.width = settings.width + 'px';
			if($system.is.digit(settings.height)) pane.body.style.height = settings.height + 'px';

			//Override settings on the window
			if($system.is.digit(settings.left)) pane.object.style.left = (settings.left > 0 ? settings.left : 0) + 'px';
			if($system.is.digit(settings.top)) pane.object.style.top = (settings.top > 0 ? settings.top : 0) + 'px';

			//Set the locked state
			if(settings.locked == 1) $system.tool.lock(id, true);

			//Set the size in style explicitly for internal nodes' "width" and "height" to work at "100%"
			pane.body.style.width = pane.body.clientWidth + 'px';
			pane.body.style.height = pane.body.clientHeight + 'px';

			if(pane.displayed.bar && !pane.displayed.body) $system.tool.hide(id, 'body', true); //Hide the body if set so

			pane.object.style.visibility = ''; //Make it visible upon fading in
			$system.window.fade(id, false, execute, false, true, quick); //Fade in the window and execute the specified function as it completes

			return pane;
		}

		this.fade = function(id, direction, execute, destroy, stay, quick) //Fades in and out a window
		{
			var log = $system.log.init(_class + '.fade');

			if(direction === undefined) direction = !$system.node.hidden(id); //Calculate the opposite condition
			if(!$system.node.id(id)) return false;

			if(!($system.window.list[id] instanceof _window)) //Make sure it's an application window
				return log.dev($global.log.warning, 'dev/window/wrong', 'dev/window/wrong/solution', [id]);

			if(__node.fading[id]) return; //Do not let it fade while fading
			if(!direction && !stay) $system.window.raise(id); //Raise the window if appearing

			var completion = function(id, direction, execute, destroy) //On fading completion
			{
				if(direction)
				{
					if(destroy)
					{
						$system.node.remove(id); //Completely let go of the window
						delete $system.window.list[id];
					}
					else if($global.user.pref.fade) $system.node.hide(id, true); //Hide the window
				}

				if(typeof execute == 'function') execute(); //Run the queued function
			}

			var run = $system.app.method(completion, [id, direction, execute, destroy]);

			if(!$global.user.pref.fade || quick === true) //If fading is disabled
			{
				$system.node.fade(id, direction, run, undefined, quick); //Simply change the window's visibility
				return;
			}

			var rows = $system.node.id(id).rows; //Get the window's cell rows
			var index = 0;

			var pane = $system.window.list[id];

			if(!direction) //If fading in
			{
				$system.node.hide(id, false); //Make the window visible

				for(var i = 0; i < rows.length; i++) //For every row of the window
				{
					var cells = rows.item(i).cells; //The cells in the row

					for(var j = 0; j < cells.length; j++) //Recover the window color
						$system.image.background(cells.item(j), $system.window.list[id].design + _spots[index++]);
				}
			}
			else //If fading out
			{
				for(var i = 0; i < rows.length; i++) //For every row of the window
				{
					var cells = rows.item(i).cells; //The cells in the row

					//It is much easier to make the whole window invisible but keep the body and the bar visible to achieve the effect,
					//but IE6/7 breaks horribly with such effect, thus cancelling the background completely
					for(var j = 0; j < cells.length; j++) $system.image.background(cells.item(j), null);
				}
			}

			if(pane.displayed.bar) $system.node.fade(pane.bar.id, direction); //Change bar visibility if displayed
			else if(pane.displayed.body) $system.node.fade(pane.body.id, direction); //Change body visibility if displayed

			return $system.node.fade(id, direction, run);
		}

		this.raise = function(node, value, over, quick) //Brings a window to the foremost
		{ //TODO - Realign all the window height into sequential numbers to avoid big numbers (Could do that on server side on user conf loading)
			var log = $system.log.init(_class + '.raise');
			node = $system.node.target(node);

			if(!$system.is.element(node)) return log.param();
			if(value === undefined && $system.is.digit($system.window.depth) && $system.window.depth != 0 && $system.is.digit(node.style.zIndex) && node.style.zIndex == $system.window.depth) return false; //Do not keep raising the same object

			if($system.is.digit(value))
			{
				node.style.zIndex = value; //Set to the manually given value
				if(value > $system.window.depth) $system.window.depth = value; //Keep the highest value
			}
			//For window border clone to be on the same height as the window not to increase the depth count to avoid repeated raise
			else if(over) node.style.zIndex = $system.window.depth;
			else node.style.zIndex = ++$system.window.depth; //Set the window's z axis to the highest value

			if(quick !== true && $system.window.list[node.id]) $system.window.save(node.id, {depth : $system.window.depth});
			return $system.window.depth;
		}

		this.save = function(id, parameter) //Saves the window configuration
		{
			var log = $system.log.init(_class + '.save');

			if(!$system.is.md5($global.user.ticket)) return false;
			if(!$system.is.id(id) || !$system.is.object(parameter)) return log.param();

			if(!$system.node.id(id)) return false; //If the window does not exist or vanished, quit

			if($system.window.list[id].fixed) return true; //For fixed window, do not save configurations
			parameter.id = id; //Join the ID to the parameters

			//Send the request to save the window position
			$system.network.send($system.info.root + 'server/php/front.php', {task : 'window.save', section : 'window'}, parameter, $system.gui.check);
		}

		this.sink = function(id) //Sinks the window position behind others (Raise other windows' depth in order to avoid negative depth)
		{
			var log = $system.log.init(_class + '.sink');
			if(!$system.node.id(id) || !$system.window.list[id]) return log.param();

			var list = $system.window.list; //List of windows
			var user = $system.is.md5($global.user.ticket);

			var inspect = function(depth)
			{
				var covered = [];
				for(var i in list) if(list[i].object.style.zIndex == depth) covered.push(i); //If a same height exists, push them by 1 height

				depth++; //Increase the height by 1
				if(covered.length) inspect(depth); //If windows are supposed to be pushed, inspect the next height

				for(var i = 0; i < covered.length; i++)
				{
					list[covered[i]].object.style.zIndex = depth; //Increase the height
					if(user) $system.window.save(list[covered[i]].object.id, {depth : depth}); //Save the window information
				}
			}

			inspect(0); //Check if any other window has a depth of 0, and so on
			if(!$system.node.id(id).style.zIndex) return; //If it is already lowest, quit here

			$system.window.raise(id, 0); //Set the specified window's depth as the lowest possible depth
			if(user) $system.window.save(id, {depth : 0}); //Save the window information
		}

		this.stick = function() //Keeps a window on top of other windows all the time
		{
			//TODO
		}
	}

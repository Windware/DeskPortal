
	$self.tool = new function() //Toolbar button and related class
	{
		var _class = $id + '.tool';

		var _fade = {window : {}, part : {}}; //To keep which window is fading a part in or out

		this.create = function(id) //Load an application saving its state
		{
			var log = $system.log.init(_class + '.create');
			if(!$system.is.id(id)) return log.param();

			//Save the load state asynchronously
			$system.network.send($system.info.root + 'server/php/front.php', {task : 'tool.create', section : 'used'}, {id : id, loaded : 1});

			$system.user.conf([id]); //Load the configuration for this application
			$system.app.load(id, null, true); //Load the application
		}

		this.display = function(id) //Create HTML node for the given app ID history
		{
			var log = $system.log.init(_class + '.display');
			if(!$system.is.id(id)) return log.param();

			var language = $system.language.strings($id);

			var cache = $system.language.file(id, 'history.xml'); //History change list cache as XML nodes
			if(!cache) return language['history/missing'];

			var category = ['current', 'plan']; //History types
			var target = ['user', 'dev']; //History target audience

			var section = $system.array.list('new change fix bug'); //Type of change history

			var container = document.createElement('div'); //Create temporary container to create nodes inside
			container.className = $id + '_info_changes';

			for(var i = 0; i < category.length; i++)
			{
				var list = $system.dom.tags(cache, category[i])[0];
				var title = null; //Indicate that the title header is displayed already

				for(var j = 0; j < target.length; j++)
				{
					var toward = $system.dom.tags(list, target[j])[0];

					for(var k = 0; k < section.length; k++)
					{
						var type = $system.dom.tags(toward, section[k]);
						if(!type.length) continue;

						if(!title) //Only display the title header if there are any entries
						{
							var header = document.createElement('h2'); //Set the section header
							$system.node.text(header, language[category[i]]);

							title = container.appendChild(header);
						}

						var header = document.createElement('h3'); //Set the section header

						var part = language[section[k]]; //Section name
						if(target[j] == 'dev') part += ' (' + language[target[j]] + ')';

						$system.node.text(header, part);
						container.appendChild(header);

						var sector = document.createElement('ul');
						sector.className = $id + '_changes';

						for(var l = 0; l < type.length; l++) //For each of the entries
						{
							var summary = $system.dom.attribute(type[l], 'summary');
							var detail = $system.dom.text(type[l]);

							if(!summary || !detail) continue;
							var dot = document.createElement('li');

							$system.node.text(dot, summary); //Display the summary
							$system.tip.set(dot, $id, 'blank', [detail], true); //Give the detail as a tooltip

							dot.style.cursor = 'help';
							sector.appendChild(dot);
						}

						container.appendChild(sector);
					}
				}
			}

			return container;
		}

		this.fade = function(id, direction, callback) //Opens or closes a window
		{
			var log = $system.log.init(_class + '.fade');
			if(!$system.is.id(id) || !$system.node.id(id)) return log.param();

			var state = $system.node.hidden(id); //Find current status
			if(direction === undefined) direction = !state;

			if(!_fade.window[id] && $system.is.md5($global.user.ticket) && direction != state) //Save the displayed state
				$system.network.send($system.info.root + 'server/php/front.php', {task : 'tool.fade', section : 'used'}, {id : id, loaded : !direction ? 1 : 0});

			_fade.window[id] = true;

			var run = function(id, direction, callback)
			{
				delete _fade.window[id];

				if(!direction) $system.window.raise(id); //If fading in, raise the window to the front most
				$system.app.callback(log.origin, callback);
			}

			return $system.window.fade(id, direction, $system.app.method(run, [id, direction, callback])); //Fade the window in or out
		}

		this.hide = function(id, part, quick) //Hides a component from a window
		{
			var log = $system.log.init(_class + '.hide');
			if(!$system.is.id(id) || !$system.node.id(id) || !$system.window.list[id]) return log.param();

			switch(part)
			{
				case 'bar' : var other = 'body'; break; //When hiding the toolbar

				case 'body' : var other = 'bar'; break; //When hiding the content area

				default : return log.param(); break;
			}

			var reference = $system.window.list[id]; //Reference to the window object
			var visibility = reference.visible(part); //Current visibility of the part

			$system.tip.clear(); //Remove any tips displayed

			if(!reference.visible(other)) return log.dev($global.log.notice, 'dev/tool/both', '', [id]); //If both parts tries to hide, don't
			if(_fade.part[id]) return log.dev($global.log.notice, 'dev/tool/fade', 'dev/tool/fade/solution', [id]); //If other parts are fading in/out, quit

			_fade.part[id] = true; //Keep the fade state to be on

			reference.displayed[part] = !visibility; //Set the display state
			var style = $system.node.id(id).style; //Window's style

			var base = $id + '_' + id; //Base node ID part
			var node = $system.node.id(base + '_' + part); //The HTML node of the target

			var alteration = function(id, part, visibility) //Triggered after the fade completes
			{
				if(part == 'body') //When fading the content area
				{
					$system.image.set($system.node.id(base + '_hide').firstChild, $system.info.devroot + 'graphic/' +  (visibility ? 'recover' : 'hide') + '.png'); //Flip the graphic
					$system.tip.set(base + '_resize', $id, visibility ? 'static' : 'resize'); //Flip the tip if window is not locked

					$system.tip.set(base + '_invisible', $id, visibility ? 'unremovable' : 'remove'); //Flip the tip on the right upper corner
					$system.tip.set(base + '_hide', $id, visibility ? 'show' : 'drop'); //Flip the tip message
				}
				else //When fading the toolbar
				{
					$system.tip.set(base + '_invisible', $id, visibility ? 'restore' : 'remove'); //Flip the tip message
					node.parentNode.style.height = '1px'; //Reset the size to shrink the toolbar area (webkit doesn't like 0px : as of Safari 3)
				}

				delete _fade.part[id]; //Let go of the fade state
			}

			//Change the visibility and swap the tip message on completion
			var completion = $system.app.method(alteration, [id, part, visibility]);
			$system.node.fade(base + '_' + part, visibility, completion, false, quick);

			if(quick === true) return true; //If user did not initiate the action, quit here

			var parameter = {}; //Set the visibility state for saving
			parameter[part] = visibility ? 0 : 1;

			return $system.window.save(id, parameter); //Save the visibility state
		}

		this.history = function(id, version) //Display the version history
		{
			var log = $system.log.init(_class + '.history');
			if(!$system.is.id(id) || !$system.window.list[id] || !$system.is.version(version)) return log.param();

			if(!$system.conf.swap(id, 'history')) return false; //Swap to the history tab
			$system.node.id($id + '_info_version_' + id).value = version; //Set the chosen version

			var app = $system.app.component(id)[0]; //Name of the app
			var lookup = app + '_' + version; //Lookup ID of the history of the app to display

			var set = function(id, lookup) //Set the history information on the node
			{
				$system.node.id($id + '_info_history_' + id).innerHTML = '';
				return $system.node.id($id + '_info_history_' + id).appendChild($system.tool.display(lookup));
			}

			//If the specified version's history is cached, display it
			if($system.is.object($system.language.file(lookup, 'history.xml'))) return set(id, lookup);

			var step = version.split('_'); //Grab all of the revision histories

			try { var rev = Number($global.app[app].version[step[0]][step[1]]); } //Get amount of revisions

			catch(error) { var rev = 0; }

			if(!rev) return set(id, lookup); //Display error without any files to get
			var list = []; //History files to load

			//Find list of history files to get and load them
			for(var i = 0; i < rev; i++) list = list.concat($system.language.pick(lookup.replace(/\d+$/, '') + i, 'history.xml'));
			$system.network.fetch(list, $system.app.method(set, [id, lookup]));
		}

		this.info = function(id) //Show application info/tool panel
		{
			var log = $system.log.init(_class + '.info');
			if(!$system.is.id(id) || !$system.app.library(id) || !$global.top[id]) return log.param();

			var node = [$id, id, 'info'].join('_'); //The node ID of the panel
			if($system.node.id(node)) return $system.window.fade(node); //If created before, fade the window

			var language = $system.language.strings($id); //Load system localized strings

			var apply = function(id) //Create the info tabs
			{
				var space = $system.node.id([$id, id, 'info_tabs'].join('_')); //Section tabs

				//NOTE : 'exp' (data export) and 'migrate' (database migration) are not implemented in this version
				var section = $system.array.list('about conf manual history'); //List of all tabs - FIXME : Removed 'review'

				var hover = function(tab, id)
				{
					var info = id ? $global.top[id].info : null;

					tab.style.backgroundColor = $system.is.id(id) && $system.is.color(info.window) ? '#' + info.window : '';
					tab.style.color = $system.is.id(id) && $system.is.color(info.color) ? '#' + info.color : '';
				}

				__conf.pages[id] = {}; //Tab page caches

				for(var i = 0; i < section.length; i++)
				{
					__conf.pages[id][section[i]] = {};

					switch(section[i])
					{
						case 'conf' : case 'review' : //Do not show user related tabs when not logged in
							if(!$system.is.md5($global.user.ticket)) continue;
						break;

						case 'manual' : //Requires valid pages
							var request = $system.network.item($global.top[id].info.devroot + 'template/' + section[i] + '/*.html', true);

							for(var j = 0; j < request.length; j++) //Cache pages
								if(request[j].valid()) __conf.pages[id][section[i]][request[j].file.replace(/\.html$/, '')] = $system.language.apply(id, request[j].text, section[i] + '.xml');
						break;
					}

					var tab = document.createElement('a'); //Create the tab to switch the section
					tab.id = [$id, id, 'tab', section[i]].join('_');

					var value = [$id, $system.image.source($id, section[i] + '_grey.png'), section[i]];
					tab.innerHTML = $system.text.format('<img class="%%_icon" src="%%" name="%%" /> ', value) + language[section[i]];

					tab.onclick = $system.app.method($system.conf.swap, [id, section[i]]);

					//Make flipping colors
					tab.onmouseover = $system.app.method(hover, [tab, id]);
					tab.onmouseout = $system.app.method(hover, [tab, false]);

					space.appendChild(tab);
				}
			}

			var info = $global.top[id].info; //Reference to the application's information array
			var title = language.tool + ' [' + info.title + ']'; //Window title

			var load = $system.app.method($system.conf.swap, [id, 'about']);
			var prepare = $system.app.method(apply, [id]);

			$system.window.create(node, title, $system.info.template.panel.replace(/%app%/g, id), info.color, info.hover, info.window, info.border, true, null, null, 800, 400, true, true, true, load, prepare, true);
		}

		this.lock = function(id, quick) //Locks the window from moving : TODO - Change the icon to a pin instead of a lock?
		{
			var log = $system.log.init(_class + '.lock');

			var pane = $system.window.list[id]; //The window reference
			var node = $system.node.id([$id, id, 'lock'].join('_')); //The lock link node

			if(node.firstChild && $system.is.element(node.firstChild, 'img')) //If it has a lock graphic
			{
				var image = pane.locked ? 'unlock' : 'lock'; //Pick an image according to the lock state
				$system.image.set(node.firstChild, $system.info.devroot + 'graphic/' + image + '.png');
			}

			$system.tip.set(node, $id, pane.locked ? 'lock' : 'unlock'); //Flip the tip messages
			log.user($global.log.info, pane.locked ? 'user/tool/unlock' : 'user/tool/lock', '', [pane.title]);

			pane.locked = !pane.locked; //Flip the locked state
			if(quick !== true) $system.window.save(id, {locked : pane.locked ? 1 : 0}); //Save the locked state
		}

		this.sink = function(id) { return $system.window.sink(id); } //Puts the window behind others
	}

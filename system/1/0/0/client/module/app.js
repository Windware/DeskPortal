
	$self.app = new function() //Application related class
	{
		var _class = $id + '.app';

		var _system = function(system, id, process, log) //Load another system and run the loading process
		{
			var fail = function(problem, param) //On failure
			{
				if(process != 'load') $system.app.unload(id);
				return log.dev($global.log.error, problem, 'dev/depend/solution', param);
			}

			if(!$system.app.library(system)) return fail('dev/depend', [system]); //Try to load the new system
			if(typeof $global.loader[process][system] != 'function') return fail('dev/registered', [system]); //Check the load process function exists

			return $global.loader[process][system](id); //Use that system version to process the loading
		}

		this.callback = function(source, callback, param) //Run a callback function safely
		{
			if(!$system.is.text(source)) return false;
			if(typeof callback != 'function') return true;

			try { return $system.is.array(param) ? $system.app.method(callback, param)() : callback(); }

			catch(error)
			{
				var log = $system.log.init(_class + '.callback');
				return log.dev($global.log.error, 'dev/callback', 'dev/callback/solution', [source, $system.browser.report(error)]);
			}
		}

		//Gets the name and the version of the application from an ID string
		this.component = function(id) { return String(id).match(/^([a-z\d]+)_(\d+_\d+_\d+)$/) ? [RegExp.$1, RegExp.$2] : null; }

		this.conf = function(id, pair) //Sets the application configuration value
		{
			var log = $system.log.init(_class + '.conf');
			if(!$system.is.id(id) || !$system.is.object(pair)) return log.param();

			if(!$global.user.conf[id]) $global.user.conf[id] = {}; //Update the information
			for(var key in pair) if($system.is.text(key)) $global.user.conf[id][key] = pair[key];

			//Sending 'id' as GET not to mix up parameters
			return $system.network.send($system.info.root + 'server/php/front.php', {task : 'app.conf', id : id}, pair);
		}

		this.library = function(id) //Load the application library
		{
			var log = $system.log.init(_class + '.library');
			if(!$system.is.id(id)) return log.param();

			if($system.is.object($global.top[id])) return true //If already loaded, quit as a success
			var $private = {log : log, system : $system, id : id}; //Placeholder to keep variable names before the 'eval' process

			$private.failure = function(message, solution, param) //Temporary function to report error on library loading
			{
				$system.app.unload($private.id); //Run the unload process
				return $private.log.dev($global.log.error, message, solution, param);
			}

			$private.info = $system.app.path(id) + 'client/info.js'; //Get the application information
			$private.request = $system.network.item($private.info);

			if(!$private.request.made) //If the file is never requested
			{
				$system.network.fetch([$private.info]);
				$private.request = $system.network.item($private.info);
			}

			if(!$private.request.valid()) return $private.failure('dev/find', '', [$private.info]);

			new function(_class, log, id) //Run in a closed environment to allow $id/$self/$system re-declaration inside
			{
				//Give these local to the application
				var $self;
				var $id = $private.id; //Own ID
				var $system = $private.system;

				$global.top[$id] = new function() //Create the application object and declare itself as '$self'
				{
					$self = this;
					$self.info = {}; //Informational array
				}

				//Create application preference hash
				if(!$system.is.object($global.user.conf[$id])) $global.user.conf[$id] = {};

				$private.component = $system.app.component($id); //Get the application name and version

				with($self) //Define the application information
				{
					info.name = $private.component[0]; //Application name
					info.version = $private.component[1]; //Application version

					info.id = $id; //ID of the application
					info.require = []; //Client side requirement declaration

					info.bar = true; //Toolbar presence
					info.border = info.color = info.hover = info.window = undefined; //Window color

					info.center = false; //Center alignment
					info.up = false; //Vertical alignment

					info.width = info.height = undefined; //Window geometry
					info.top = info.left = undefined; //Window position

					info.root = $system.app.path($id); //Top folder for the application

					//Browser specific folder

					info.preload = []; //List of preloading files
					info.depend = []; //List of dependencies on other applications

					info.system = null; //Indicates which system the application relies on
					info.template = {}; //List of HTML template caches

					info.devroot = $global.user.conf[$id] && $global.user.conf[$id].theme ? $global.user.conf[$id].theme : info.root + 'component/default/';
					info.devroot += $system.browser.type + '/';
				}


				try { with($self.info) eval($private.request.text); } //Load the info script with its own 'info' name space applied

				catch(error) { return $private.failure('dev/info', 'dev/check', [$id, $system.browser.report(error)]); }

				if($id.match(/^system_/)) $self.info.system = $id; //If loading a system, the dependant system will be itself
				else if($system.is.array($self.info.depend))
				{
					for(var i = 0; i < $self.info.depend.length; i++) //Find out which system the application depends on
						if($self.info.depend[i].match(/^system(_\d+){3}$/)) $self.info.system = $self.info.depend[i];
				}

				//If it cannot find any system dependency, report error
				if(!$self.info.system) return $private.failure('dev/depend/specify', 'dev/depend/specify/solution', [$id]);

				if($system.info.id != $self.info.system) //If the current system differs from the application's depending system
				{
					$private.system = $self.info.system; //Keep the depending system version
					$system.app.unload($id); //Unload partially configured state

					return _system($private.system, $id, 'library', $private.log); //Load the new system and the app
				}

				$system.app.prepare($id); //Preload the rest of its files
				$private.request = $system.network.item($self.info.devroot + 'style/style.js');

				try { with($self.info) eval($private.request.text); } //Load the style script with its own 'info' name space applied

				catch(error) { return $private.failure('dev/style', 'dev/check', [$id, $system.browser.report(error)]); }

				$global.depend[$id] = []; //Declare the reverse dependency list array

				for(var i = 0; i < $self.info.depend.length; i++) //For each of the depending applications
				{
					if(!$system.app.library($self.info.depend[i])) //If it could not load the library, report error
						return $private.failure('dev/depend/load', 'dev/depend/load/solution', [$self.info.depend[i], $id], [$self.info.depend[i]]);

					$global.depend[$self.info.depend[i]].push($id); //Add the application into the reverse dependency list
				}

				$self.info.title = $system.language.file($id, 'title.txt');
				$self.info.title = $self.info.title ? $self.info.title.replace(/\s+$/, '') : $id; //Set its title or use its ID if not found

				$self.info.desc = $system.language.file($id, 'info.txt');
				$self.info.desc = $self.info.desc ? $self.info.desc.replace(/\s+$/, '') : ''; //Set its description

				if(!$global.app[$id]) $global.app[$id] = {title : $self.info.title}; //Create application informational hash

				$private.app = $id.replace(/^(\w+?)_.+/, '$1'); //Get app name
				if(!$global.app[$private.app]) $global.app[$private.app] = {}; //Make app info hash

				var request = $system.network.item($self.info.devroot + 'template/module/*.html', true); //Load other templates

				for(var i = 0 ; i < request.length; i++)
				{
					if(!request[i].valid())
					{
						log.dev($global.log.warning, 'dev/template', 'dev/template/solution', [$id, request[i].file]);
						continue;
					}

					var load = request[i].file.replace(/\.html$/, ''); //Take the file name as the template name
					$self.info.template[load] = $system.language.apply($id, request[i].text); //Apply the language and stack on the info template hash
				}

				$private.code = ''; //Entire client library codes

				var request = $system.network.item($self.info.root + 'meta.xml'); //Load the meta information
				if(!request.valid()) return $private.failure('dev/meta', 'dev/meta/solution', [$id]);

				$self.info.meta = request.xml; //Hold the meta information as XML
				$self.info.category = $system.dom.attribute($system.dom.tags($self.info.meta, 'info')[0], 'category'); //Pick its category

				//Load all of the module scripts by asking for partial matches
				var request = $system.network.item($self.info.root + 'client/module/', true);

				var load = $system.array.list('conf manual run init'); //Add required scripts for loading
				for(var i = 0; i < load.length; i++) request.push($system.network.item($self.info.root + 'client/' + load[i] + '.js'));

				for(var i = 0; i < request.length; i++) //Concatenate all of the module codes
				{
					if(request[i].made && !request[i].valid(true)) //If network error occurs (File missing is accepted)
						return $private.failure('dev/library/network', 'dev/library/network/solution', [$self.info.root]);

					$private.code += request[i].text + "\n";
				}

				i = load = request = undefined; //Clear temporary variables before the 'eval'
				$system = $global.top[$self.info.system]; //Declare the depending system reference

				try { eval($private.code); } //Run the codes

				catch(error) { return $private.failure('dev/library/init', 'dev/library/init/solution', [$id, $system.browser.report(error)]); }

				$system.language.strings($id, 'tip.xml'); //Load the tip strings from XML if any exists
			}

			return true; //Loading succeeded
		}

		this.load = function(id, execute, front, quick) //Load and run an application : TODO - Make it less synchronous to avoid interface slow down
		{
			var log = $system.log.init(_class + '.load');

			if(!$system.is.id(id)) return log.param();
			if(id.match(/^system_/)) return false; //Do not load any interface for 'system' app

			if($system.node.id(id)) //If the application already exists as a node, quit
			{
				if(typeof execute == 'function') execute();
				return log.dev($global.log.info, 'dev/exists', '', [id], null, true);
			}

			var $private = {id : id, execute : execute, log : log, front : front, quick : quick, system : $system}; //Gather variables on a single name space

			$private.run = function() //Run from setTimeout to avoid locking
			{
				//If library fails to load, quit
				if(!$system.app.library(id) || !$system.is.object($global.top[id]) || !$system.is.object($global.top[id].info))
				{
					var app = $system.app.component(id);
					var name = $global.app[id] && $global.app[id].title || id;

					$system.gui.alert($id, 'user/app/fail/title', 'user/app/fail/message', undefined, null, [name]); //Make an alert (FIXME - This avoids another alert of this to display if the previous one exists)
					$system.app.unload(id);

					return $private.log.dev($global.log.error, 'dev/library/object', 'dev/library/object/solution', [id]);
				}

				var info = $global.top[id].info; //Shortcut to the application's informational hash
				log.user($global.log.info, 'user/app/load', '', [info.title]);

				//If it's using another system, load the new system and the app with it
				if(info.system != $system.info.id) return _system(info.system, id, 'load', $private.log);

				if(!$system.is.text(info.devroot) || !$system.is.text(info.id)) //If it does not have proper information, quit
				{
					$system.gui.alert($id, 'user/app/fail/title', 'user/app/fail/message', undefined, null, [id]); //Make an alert 
					$system.app.unload(id);

					return $private.log.dev($global.log.error, 'dev/object', 'dev/object/solution', [info.id]);
				}

				$private.ready = function($private, execute, front, quick, id, log, info) //Run the application code just before the window goes live
				{
					var $id = $private.id; //Set its own ID
					var $self = $global.top[$id]; //Refer to its own self
					var $system = $global.top[$self.info.system]; //Reference to the dependant system

					if(!$private.system.is.object($private.system.node.id($id)))
						return $private.log.dev($global.log.error, 'dev/window', 'dev/window/solution', [$id]);

					if(typeof $self.run != 'function') //If initial execute code does not exist
					{
						if(typeof $private.execute == 'function')
						{
							try { $private.execute(); } //Run the callback directly

							catch(error)
							{
								return $private.log.dev($global.log.error, 'dev/callback', 'dev/callback/solution', [$private.id, $private.system.browser.report(error)]);
							}
						}

						return true;
					}

					try { $self.run($private.execute); } //Run the initial execution code queueing the callback

					catch(error)
					{
						$private.system.gui.alert($private.system.info.id, 'user/app/fail/title', 'user/app/fail/message', undefined, null, [$private.id]); //Make an alert on the user side
						$private.system.app.unload($private.id); //Unload the application

						return $private.log.dev($global.log.error, 'dev/execute', 'dev/execute/solution', [$private.id, $private.system.browser.report(error)]);
					}
				}

				if(!$system.is.color(info.color)) info.color = ''; //Check for application strings color
				if(!$system.is.color(info.hover)) info.hover = ''; //Check for application strings hovered color

				if(!$system.is.color(info.window)) info.window = ''; //Check for application window color
				if(!$system.is.color(info.border)) info.border = ''; //Check for application border color

				if(typeof info.bar != 'boolean') info.bar = false; //No toolbar by default if not specified
				if(!$system.is.text(info.title)) info.title = info.id; //Check for its title

				$system.style.add(id, 'common.css'); //Load its style sheet
				$system.style.add(id, $system.browser.engine + '.css'); //Add its engine specific style sheet

				$private.request = $system.network.item(info.devroot + 'template/body.html');
				if(!$private.request.valid(true)) return $private.log.dev($global.log.error, 'dev/body', 'dev/body/solution', [$private.id]);

				var body = $system.language.apply($private.id, $private.request.text);

				//Build the application window
				$system.window.create(id, info.title, body, info.color, info.hover, info.window, info.border, info.bar, info.left, info.top, info.width, info.height, info.center, info.up, true, null, $system.app.method($private.ready, [$private]), undefined, $private.quick);
				if($private.front === true) $system.window.raise($private.id); //If loaded manually, bring to the front
			}

			setTimeout($private.run, 0); //Do not let other operations wait
		}

		this.method = function(execute, parameter) //Return the function as a reference with parameters passed for later use
		{
			if(typeof execute != 'function' || !(parameter instanceof Array))
			{
				var log = $system.log.init(_class + '.method');
				return log.param(function() { }); //Return an empty function
			}

			//Return the function and append the parameters given when executed
			return function() { return execute.apply(this, parameter.concat(Array.prototype.slice.call(arguments))); }
		}

		this.path = function(id) //Returns the top directory for an application (Same as '$global.top[id].info.root' if defined)
		{
			if(!$system.is.text(id) || !$system.is.id(id)) return '';
			return id.replace(/_/g, '/') + '/'; //Return the root path
		}

		this.prepare = function(id, core) //Preload the initial files
		{
			var log = $system.log.init(_class + '.prepare');
			if(!$system.is.text(id) || !$system.is.object($global.top[id])) return log.param();

			var load = []; //List of files to preload
			var info = $global.top[id].info; //The info hash of the application

			//'core' is only set to 'true' when loading the core system to avoid reloading already loaded scripts
			//It does not hurt the system if they are loaded again,
			//but it will waste the memory by keeping the preloaded files unused and tiny bandwidth associated fetching the redundant content
			if(!core) load.push(info.root + 'client/*.js', info.root + 'client/module/*.js'); //Add every scripts

			//Add all of the specified preloading files
			if(info.preload instanceof Array) for(var i = 0; i < info.preload.length; i++) load.push(info.root + info.preload[i]);
			load.push(info.devroot + 'template/*.html', info.devroot + 'template/*/*.html'); //Load all of the HTML files

			//Load other default files and stylesheets
			load.push(info.root + 'meta.xml', info.devroot + 'graphic/icon.png');
			load.push(info.devroot + 'style/style.js', info.devroot + 'style/common.css', info.devroot + 'style/' + $self.browser.engine + '.css');

			var picked = $system.language.pick(id, '*.*'); //TODO - Likely not necessary to download English fallback files for other languages
			for(var i = 0; i < picked.length; i++) load.push(picked[i]);

			for(var i = 0; i < info.depend.length; i++) //Load any application's info file that the application depends on
				if(!$global.top[info.depend[i]]) load.push($system.app.path(info.depend[i]) + 'client/info.js');

			return $system.network.fetch(load); //Cache the lists
		}

		this.reload = function() //Reload system variable : TODO
		{
			if($global.system == $id) return true; //If this is the core system, quit
			//If this system is loaded from another system, variables may not exist those are used within this system
			//And this function will make sure that they exist for this system version to work
			//Ex : If the other version of system never declares "$id" when loading this system for instance, will corrupt this system
			//Should be functionally equivalent to $system.app.load($id) without remote access and redundant operations
		}

		this.theme = function(id, theme) //Change to a new theme : FIXME - Not implemented
		{
			var log = $system.log.init(_class + '.theme');

			//Change $global.user.conf[id].theme parameter
			//Notify the need to reload the app
		}

		this.unload = function(id, execute) //Unload the application and all applications depending on it
		{
			var log = $system.log.init(_class + '.unload');

			if(!$system.is.id(id)) return log.param();
			if(!$global.top[id]) return true;

			var $private = {log : log, id : id, execute : execute, system : $system}; //Temporary variables to avoid variable mix up in the 'unload' script
			var system = $global.top[id].info.system; //The system used by the application

			//Unload the system with the version it depends on
			if($system.is.id(system) && $system.info.id != system) return _system(system, id, 'unload', log);

			$private.remove = function(id, _class, execute, log, system) //Remove the application
			{
				var $system = $global.top[$private.system.info.system]; //Reference to the application's system
				var $self = $global.top[id]; //Refer to the application itself
				var $id = id; //Set its own ID

				if($global.depend && $global.depend[id] instanceof Array) //Just to avoid possible errors
				{
					for(var i = 0; i < $global.depend[id].length; i++) //For each of the depended application
					{
						//Unload any application that relies on the unloading application first and clear its dependency if successful
						if($private.remove($global.depend[id][i])) delete $global.depend[id][i];
					}
				}

				$private.request = $private.system.network.item($private.system.info.root + 'client/unload.js'); //Load the unload script from cache
				i = undefined; //Clear out the local variable before the 'eval'

				if($private.request.valid()) //If the unloading script exists
				{
					try { eval($private.request.text); } //Run the unloading script

					catch(error) //Stop unloading in case of errors
					{
						$private.log.user($global.log.notice, 'user/app/unload/fail', '', [$global.top[id].info.title]);
						return $private.log.dev($global.log.error, 'dev/unload', 'dev/check', [$private.system.info.id, $private.system.browser.report(error)]);
					}
				}

				$private.lose = function($private, $self, $system, id, clean) //Function to unload the objects and execute the pending function
				{ //FIXME - Let go of periodical scheduling too
					//Remove the stylesheets
					$private.system.style.remove(id, 'common.css');
					$private.system.style.remove(id, $private.system.browser.engine + '.css');

					if(typeof $private.execute == 'function')
					{
						try { $private.execute(); }

						catch(error)
						{
							$private.log.user($global.log.notice, 'user/app/unload/fail', '', [$global.top[id].info.title]);
							$private.log.dev($global.log.error, 'dev/unload/execute', 'dev/check', [id, $self.browser.report(error)]);
						}
					}

					$private.log.user($global.log.notice, 'user/app/unload', '', [$global.top[id].info.title]);

					//Clear out the application from the dependency list and the global registration
					delete $global.depend[id];
					delete $global.top[id];

					return true; //Report success, even if style sheet removal fails for some reason
				}

				for($private.index in $private.system.window.list) //Close any related windows
					if($private.index.match(RegExp('^(' + $private.system.info.id + '_)?' + id + '_')))
						$private.system.window.fade($private.index, true, null, true);

				var clean = $private.system.app.method($private.lose, [$private, $self, $system, id]); //Method to call for final clean up

				//Remove the application window if it exists and do the clean up
				return $private.system.node.id(id) ? $private.system.window.fade(id, true, clean, true) : clean();
			}

 			$private.remove(id); //Remove the application inside a function
		}
	}


	$self.language = new function() //Language related class
	{
		var _class = $id + '.language';

		var _cache = {}; //A list to keep caches of the retrieved language files

		var _text = {}; //A hash to keep the result from language string retrieval

		this.supported = []; //List of supported languages

		this.init = function() //Load the list of supported languages
		{
			var request = $system.network.item($system.info.root + 'resource/language.xml');
			var list = $system.dom.tags(request.xml, 'language');

			for(var i = 0; i < list.length; i++) $system.language.supported.push({name : $system.dom.attribute(list[i], 'name'), code : $system.dom.attribute(list[i], 'code')});
		}

		this.apply = function(id, text, file, language, prepare) //Apply language specific strings to a textual template
		{
			var log = $system.log.init(_class + '.apply');

			if(file === undefined) file = 'strings.xml'; //Use 'strings.xml' as default if not specified
			if(!$system.is.id(id) || !String(file).match(/\.xml$/i) || typeof text != 'string') return log.param();

			if(text === '') return ''; //Do not bother processing an empty string
			var collection = $system.language.strings(id, file, language); //Get the language specific strings from the specified file

			//A function to do replacing through regular expression
			var translator = function(phrase, match) { return collection[match] || match; }

			//Replace all the template syntaxes into actual language strings
			text = text.replace(/%string:(.+?)%/g, translator).replace(/%tip:(.+?)%/g, $system.app.method($system.tip.link, [id]));
			return $system.text.template(text, id, prepare); //Replace the variables
		}

		this.file = function(id, file, language) //Pick the language specific file content for a given file name
		{
			var log = $system.log.init(_class + '.file');
			if(file === undefined) file = 'strings.xml'; //Use 'strings.xml' as default if not specified

			if(!$system.is.path($system.app.path(id)) || !$system.is.text(file)) return log.param();
			if(!language) language = $system.language.pref(); //If no language is specified, use the user preferred language

			var tag = [id, file, language].join('/'); //Create a unique tag for the specified file to identify caches
			if($system.is.object(_cache[tag]) || $system.is.text(_cache[tag])) return _cache[tag]; //If cache exists, use it

			var list = $system.language.pick(id, file, language); //Pick the appropriate language files

			for(var i = 0; i < list.length; i++) //For each of the language files, from the least accurate language
			{
				var request = $system.network.item(list[i]); //Make a request

				//NOTE : Do not exit the loop to keep flushing out the caches made by '$system.network.fetch'
				if(request.valid() && !_cache[tag]) _cache[tag] = request.xml || request.text; //Store the response
			}

			return _cache[tag] ? _cache[tag] : false;
		}

		this.pick = function(id, file, language) //Get a list of files according to the language preference
		{
			var log = $system.log.init(_class + '.pick');
			if(file === undefined) file = 'strings.xml'; //Use 'strings.xml' as default if not specified

			var load = []; //List of files to return
			if(!$system.is.id(id) || !$system.is.text(file)) return log.param(load);

			var candidates = []; //List of possible languages

			if($system.is.language(language)) candidates.push(language.replace('_', '-'), language); //If it's a language string, add it to the candidate list
			language = $system.language.pref(); //Add the user preferred one

			for(var i = 0; i < $system.browser.language.length; i++) if($system.is.language($system.browser.language[i])) candidates.push($system.browser.language[i]); //Look through browser configured language preference

			candidates.push('en');
			candidates = $system.array.unique(candidates); //Try to crop out same entries

			for(var i = 0; i < candidates.length; i++) //For each of the candidates
			{
				var doc = $system.app.path(id) + 'document/' + candidates[i] + '/' + file; //Create the language file path
				load.push(doc); //Push it into the array
			}

			return load; //Return the result
		}

		this.pref = function() //Finds the highest language preference
		{
			var query = window.location.search.replace(/^\?/, ''); //Get the query string in the address
			if($system.is.language(query)) return query; //Specified language in the address will take the first precedence

			//Or use the one specified by the user as a preference
			if($system.is.language($global.user.pref.language)) return $global.user.pref.language;

			var language = $system.browser.language; //Otherwise, look through browser configured language preference
			for(var i = 0; i < language.length; i++) if($system.is.language(language[i])) return language[i];

			return 'en'; //Fallback to 'en' if nothing was appropriate
		}

		this.reset = function(id) //Reset the entire language cache collection for a system
		{
			var log = $system.log.init(_class + '.reset');
			if(!$system.is.id(id) || !id.match(/^system_/)) return log.param();

			for(var tag in _text) if(tag.match(RegExp('^' + id + ':'))) delete _text[tag]; //Remove the language cache

			var reload = $system.language.pick($system.info.id, '*.xml');
			reload.push($system.info.devroot + 'template/*.html')

			$system.network.fetch(reload); //Reload the entire language files
			$system.window.init();
			$system.date.init();
		}

		this.strings = function(id, file, language) //Get the language string file and retrieve the texts out of it
		{
			var log = $system.log.init(_class + '.strings');
			if(file === undefined) file = 'strings.xml'; //Use 'strings.xml' as default if not specified

			if(!$system.is.path($system.app.path(id)) || !String(file).match(/\.xml$/i)) return log.param();
			if(!language) language = $global.user.language; //If no language was specified, use the interface language

			var tag = [id, file, language].join(':'); //A tag to create a unique ID for the request for caching purpose
			if($system.is.object(_text[tag])) return _text[tag]; //If cache exists, use it

			var load = $system.language.pick(id, file, language); //Pick a list of possible language files
			_text[tag] = {}; //A hash to keep the cache

			for(var i = load.length - 1; i >= 0; i--) //For each of the language files
			{
				var request = $system.network.item(load[i]); //Get the requested file
				if(!request.valid()) continue; //If it could not be retrieved, try next

				if(!request.xml || !request.xml.firstChild) //Move onto the next language file on error
				{
					log.dev($global.log.error, 'dev/language/file', '/dev/language/file/solution', [load[i]]);
					continue;
				}

				var nodes = request.xml.firstChild.childNodes; //See if the file has a proper structure

				for(var j = 0; j < nodes.length; j++) //For each of the nodes in the XML
				{
					if(nodes[j].nodeType != 1) continue; //If only the type of the node is an element

					var name = $system.dom.attribute(nodes[j], 'name');
					var value = $system.dom.attribute(nodes[j], 'value').replace('\\n', '<br />\n');

					_text[tag][name] = $system.text.template(value, id);
				}
			}

			return _text[tag]; //Return the result
		}
	}

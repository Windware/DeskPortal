
	$self.network = new function() //Network related class
	{
		var _class = $id + '.network';

		var _item = {}; //List of files prefetched by 'this.fetch'

		var _response = function(_address, _headers, _status, _text, _xml, _state, _code) //A container to keep the result of a request
		{
			this.address = _address; //Address of the file

			this.code = _code; //Returned code in XML in a <status> node if any

			this.file = this.address.replace(/^.+\//, ''); //File name

			this.header = function(section) //Returns all or part of the header
			{
				if(!$system.is.text(section)) return this.headers; //If section is unspecified, return all

				var type = RegExp('^' + $system.text.regexp(section) + ':\s*(.+)', 'i'); //Build the regular expression for matching
				return this.headers.match(type) ? RegExp.$1 : ''; //Return the header content if found
			}

			this.headers = _headers; //Entire headers

			this.made = !!_status; //Sign that the file has been requested

			this.set = function(params) //Override the parameters after the object is created
			{
				if(!$system.is.object(params)) return false;
				for(var key in params) this[key] = params[key];
			}

			this.state = _state;

			this.status = _status; //The psuedo HTTP status code

			this.text = _text; //The textual content of the file

			this.valid = function(missing) //Report about validity of the request
			{
				//Request is valid if the file is found/not modified or if allowed, report missing file as valid as well
				return !!((this.status == 200 || this.status == 304) || missing && this.status == 404);
			}

			this.xml = _xml; //The XML node, if the file is a XML
		}

		var _empty = new _response('', '', '', '', {}, 4); //Empty request object

		//Makes a single HTTP request to grab specified files as a XML manifest and goes asynchrous if callback function is specified
		//This will only fetch the file content and 'this.item' has to be used to grab each file's content
		this.fetch = function(list, callback)
		{
			var log = $system.log.init(_class + '.fetch');
			if(!(list instanceof Array) || !$system.is.type(callback, 'function')) return log.param();

			list = $system.array.unique(list); //Crop any identical entries
			var fetch = {task : 'network.fetch', file : []}; //List of files to actually load

			for(var i = 0; i < list.length; i++) //For each of the addresses specified
			{
				//If not already cached, list up the candidates as URL parameters
				if(!(_item[list[i]] instanceof _response)) fetch.file.push(list[i]);
				else log.dev($global.log.info, 'dev/file', '', [list[i]]);
			}

			if(fetch.file.length == 0) //If the request list is empty, quit
			{
				if(typeof callback == 'function') callback();
				return log.dev($global.log.info, 'dev/network/cancel', '', null, null, true);
			}

			var reply = function(callback, request) //Function to be executed after the remote request is dispatched
			{
				if(!request.valid()) //If request fails, quit
				{
					$system.gui.alert($id, 'network/error', 'network/explain');

					var log = $system.log.init(_class + '.reply');
					return log.dev($global.log.error, 'dev/network/package', 'dev/network/package/solution');
				}

				//Retrieve the headers, minus the content type (As it doesn't represent each components' within the XML)
				var response = request.headers.replace(/^Content-Type:.+$/mi, '');
				var box = $system.dom.tags(request.xml, 'file'); //Grab the 'file' elements in the XML

				for(var i = 0; i < box.length; i++) //For each of the components
				{
					var address = $system.dom.attribute(box[i], 'name'); //Find the name of the file
					if(!address) continue; //If not specified for some reason, leave it out

					var text = $system.dom.text(box[i], true); //The body of the request
					var xml = null; //XML object to be assigned as the response

					if(address.match(/\.xml/i) && text) //If the object is a XML file and has some content
					{
						var dom = null; //Temporary XML object to parse XML from the file content

						//Load up the XML content as a string and turn it into a XML object
						if(window.ActiveXObject) //For IE
						{
							dom = new ActiveXObject('Microsoft.XMLDOM');

							dom.async = false;
							dom.loadXML(text);
						}
						else //For others
						{
							try { dom = (new DOMParser()).parseFromString(text, 'text/xml'); }

							catch(error) { } //Ignore the errors for now
						}

						if($system.is.object(dom) && dom.documentElement) //If XML was properly parsed
						{
							switch($system.browser.engine) //Get the XML node out of it against browser engines
							{
								case 'trident' : case 'presto' : xml = dom; break;

								case 'gecko' : if(dom.documentElement.tagName != 'parsererror') xml = dom; break;

								case 'webkit' : case 'khtml' : if(dom.documentElement.firstChild.nodeType == 3) xml = dom; break;
							}
						}
					}

					//Add its own content type but use the same content header from the package request for others
					var headers = $system.text.format('%%Content-Type: %%\n', [response, $system.dom.attribute(box[i], 'mime')]);

					var code = $system.dom.attribute($system.dom.tags(xml, 'status')[0], 'value'); //The response code
					if($system.is.digit(code)) code = Number(code);

					//Create the object to be cached
					_item[address] = new _response(address, headers, $system.dom.attribute(box[i], 'status'), text, xml, request.readyState, code);
				}

				if(callback)
				{
					try { callback(); } //Run the completion callback function

					catch(error) { log.dev($global.log.error, 'dev/network/callback', 'dev/check', [$system.browser.report(error)]); }
				}

				return true; //Report success
			}

			//Set the callback function for asynchronous request if specified so
			var callback = typeof callback == 'function' ? $system.app.method(reply, [callback]) : null;

			//TODO - Check if the parameter length isn't too long against web server implementation/http specification
			//TODO - If cache exists for the address as '_item[address]', set 'If-Modified-Since' and if '304' status is returned, use the cache
			//Request the files to the server as a single XML package
			//If it's asked to go asynchronous, even if there are no files to load, it will make a remote request for the callback to work
			var request = $system.network.send($system.info.root + 'server/php/front.php', fetch, null, callback);
			if(callback === null) return reply(undefined, request); //If no function is specified for it to go synchronous, run the following process
		}

		this.form = function(address) //Creates a full path against router scripts to access a file specified relatively
		{
			var query = address.split('?'); //Separate the requested file name and the query string
			var script = query[0].replace(/^.+\./, '').toLowerCase(); //Get the extension

			var type = $system.array.find($global.extensions, script) ? script : 'php'; //If a proper server side script is specified, use it, but use PHP by default
			query[1] = query[1] ? '&' + query[1] : ''; //Add its own query strings if it exists

			return $system.text.format('router-%%.%%?_version=%%&_self=%%%%', [type, $global.extensions[type], $system.info.version, query[0], query[1]]);
		}

		//Returns the cached object retrieved by 'this.fetch' and clears the cache
		//'multiple' returns multiple file results as an array (even if the result is single) if it matches the path anchored on the first letter
		this.item = function(address, multiple)
		{
			var log = $system.log.init(_class + '.item');
			if(!$system.is.path(address)) return log.param(_empty);

			if(!multiple) //If only a single address is requested
			{
				if(!(_item[address] instanceof _response)) return _empty; //If it does not exist in the cache, return an empty cache object

				var response = _item[address]; //Pick the corresponding item
				delete _item[address]; //Clean it up once loaded
			}
			else //For multiple addresses request
			{
				//Create the matching string (Let '*' match to any character but a directory separator)
				var anchor = RegExp('^' + $system.text.regexp(address).replace(/\\\*/g, '[^/]+'));
				var response = []; //List of responses to return

				for(var key in _item) //Go through the entire caches
				{
					//If it does not match partially to the given address or not a proper object, ignore
					if(!key.match(anchor) || !(_item[key] instanceof _response)) continue;

					response.push(_item[key]); //Add to the returning list
					delete _item[key]; //Clean it up once loaded
				}
			}

			return response; //Return the cached items
		}

		this.send = function(address, get, post, callback, error, header) //Grabs the file remotely via XMLHttpRequest and returns the request object
		{
			var log = $system.log.init(_class + '.send');

			if(!$system.is.text(address) || !$system.is.type(get, 'object') || !$system.is.type(post, 'object')) return log.param();
			if(!$system.is.type(callback, 'function') && callback !== null || !$system.is.type(error, 'function') || !$system.is.type(header, 'object')) return log.param();

			var method = $system.is.object(post) ? 'POST' : 'GET'; //Specify request type according to the data given
			if(method == 'POST' && $global.demo) return $system.app.callback(_class + '.send', $system.app.method(callback, [new _response(address)])); //Avoid making call for POST on demo mode

			var asynchronous = callback !== null; //Go asynchronous unless specifically specified not to

			if($system.is.object(get)) //If URL parameters are specified
			{
				var query = []; //The request string list

				for(var key in get)
				{
					if($system.is.array(get[key])) for(var i = 0; i < get[key].length; i++) query.push(encodeURIComponent(key) + '[]=' + encodeURIComponent(get[key][i]));
					else query.push(encodeURIComponent(key) + '=' + encodeURIComponent(get[key])); //Concatenate the GET key and value
				}

				address += '?' + query.join('&'); //Append to the request address
			}

			var request = $system.browser.request(); //Create a remote request object
			request.open(method, $system.network.form(address), asynchronous); //Create the request with required parameters

			var container = new _response(address); //Create the request object

			var run = function(container, request, callback, error) //Run the specified functions
			{
				if(request.readyState != 4) return true; //Ignore partial states
				//TODO - Set a timer for a duration and if the readyState 4 never reaches within the time, alert for network timeout

				var code = $system.dom.status(request.responseXML); //The response code
				if($system.is.digit(code)) code = Number(code);

				container.set({headers : request.getAllResponseHeaders(), status : request.status, text : request.responseText, xml : request.responseXML, state : request.readyState, code : code});
				if($system.gui.check(container) == -1) return false; //If the session is expired, quit processing

				//If the request was valid, including missing files
				if(container.valid(true)) return typeof callback == 'function' ? callback(container) : true;
				else //If the request failed
				{
					if(typeof error == 'function') error(container);
					$system.gui.alert($id, 'network/error', 'network/explain');

					log.user($global.log.error, 'user/network/error', 'user/network/error/solution');
					log.dev($global.log.error, 'dev/network/error', 'dev/network/error/solution', [request.status]);

					return false;
				}
			}

			//If a callback function is specified, set the function to receive the state changes
			if(asynchronous) request.onreadystatechange = $system.app.method(run, [container, request, callback, error]);

			if(method == 'POST') //If HTTP request method is POST, set the appropriate header
			{
				var send = []; //The POST data list
				request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=utf-8');

				for(var key in post)
				{
					//Concatenate the POST key and value
					if($system.is.array(post[key])) for(var i = 0; i < post[key].length; i++) send.push(encodeURIComponent(key) + '[]=' + encodeURIComponent(post[key][i]));
					else send.push(encodeURIComponent(key) + '=' + encodeURIComponent(post[key]));
				}

				send = send.join('&'); //Make it into a URL parameter
			}
			else send = null; //Do not send any data on GET request

			if($system.is.object(header)) //Set any extra headers if specified
				for(var field in header) if($system.is.text(field) && $system.is.text(header[field])) request.setRequestHeader(field, header[field]);

			request.send(send); //Send the request to the server

			container.set({made : true}); //Update the request object information
			if(!asynchronous) container.set({headers : request.getAllResponseHeaders(), status : request.status, text : request.responseText, xml : request.responseXML, state : request.readyState});

			return asynchronous ? true : container; //Return the state of the operation or the request object
		}
	}


	$self.browser = new function() //Browser specific processing
	{
		var _class = $id + '.browser';
		this.click = {}; //Click values

		this.request; //Remote request method
		this.engine = this.name = this.os = this.type = this.version = null; //Browser information

		this.language = []; //Language preference
		this.resolution = {x : window.screen.width, y : window.screen.height}; //Display resolution

		this.height = this.width = 0; //Browser screen size
		this.deselect = null; //Text deselecting function

		this.init = function() //Detect the browser type
		{
			var browser = //Detect browser engine and version
			[
				//Detect method, browser name, engine name, version search string - Borrowed from http://www.quirksmode.org/
				{opera : true, name : 'Opera', engine : 'presto', version : 'Opera'},

				{userAgent : 'Chrome', name : 'Chrome', engine : 'webkit', version : 'Chrome'},

				{userAgent : 'Omniweb', name : 'Omniweb', engine : 'webkit', version : 'Omniweb/'},

				{vendor : 'Apple', name : 'Safari', engine : 'webkit', version : 'Version'},

				{vendor : 'iCab', name : 'iCab', engine : 'webkit', version : 'iCab'},

				{vendor : 'KDE', name : 'Konqueror', engine : 'khtml', version : 'Konqueror'},

				{userAgent : 'Firefox', name : 'Firefox', engine : 'gecko', version : 'Firefox'},

				{vendor : 'Camino', name : 'Camino', engine : 'gecko', version : 'Camino'},

				{userAgent : 'Netscape', name : 'Netscape', engine : 'gecko', version : 'Netscape'},

				{userAgent : 'MSIE', name : 'Internet Explorer', engine : 'trident', version : 'MSIE'},

				{userAgent : 'Gecko', name : 'Mozilla', engine : 'gecko', version : 'rv'},

				{userAgent : 'Mozilla', name : 'Netscape', engine : 'gecko', version : 'Mozilla'}
			];

			var os = //Detect OS and browser type - FIXME : Detect mobile browsers
			[
				//Detect method, OS name, browser type
				{platform : 'Win', name : 'windows', type : 'computer'},

				{platform : 'iPhone', name : 'iphone', type : 'computer'},

				{platform : 'Mac', name : 'mac', type : 'computer'},

				{platform : 'Linux', name : 'linux', type : 'computer'},

				{platform : 'BSD', name : 'bsd', type : 'computer'}
			];

			var dev = $system.browser; //A shortcut

			for(var i = 0; i < browser.length; i++)
			{
				if(browser[i].userAgent) { if(!navigator.userAgent.match(browser[i].userAgent)) continue; }
				else if(browser[i].vendor) { if(!navigator.vendor || !navigator.vendor.match(browser[i].vendor)) continue; }
				else if(browser[i].opera) { if(!window.opera) continue; }
				else continue;

				dev.engine = browser[i].engine;
				dev.name = browser[i].name;

				var position = navigator.userAgent.indexOf(browser[i].version);
				dev.version = navigator.userAgent.substring(browser[i].version.length + position + 1).replace(/^(\d+(\.\d+)?).*/g, '$1');

				if(!dev.version)
				{
					var position = navigator.appVersion.indexOf(browser[i].version);
					dev.version = navigator.appVersion.substring(browser[i].version.length + position + 1).replace(/^(\d+(\.\d+)?).*/g, '$1');
				}

				break;
			}

			for(var i = 0; i < os.length; i++)
			{
				if(os[i].platform) { if(!navigator.platform.match(os[i].platform)) continue; }
				else continue;

				dev.os = os[i].name;
				dev.type = os[i].type;
			}

			//Give a sensible guess if none are found in case the client gives tweaked or updated browser string that is undetectable
			if(!dev.engine) dev.engine = window.ActiveXObject ? 'trident' : 'unknown';
			if(!dev.version) dev.version = 1; //Try to be old compatible way

			if(!dev.os) dev.os = window.ActiveXObject ? 'windows' : 'unknown';
			if(!dev.type) dev.type = 'computer'; //Wild guess

			dev.width = (window.innerWidth || document.documentElement.clientWidth) + 'px'; //Screen width
			dev.height = (window.innerHeight || document.documentElement.clientHeight) + 'px'; //Screen height

			//Configure the click value depending on the browser
			if(dev.engine == 'trident' || (dev.engine == 'webkit' && dev.version < 3)) dev.click = {left : 1, middle : 0, right : 2};
			else dev.click = {left : 0, middle : 1, right : 2};

			if(document.selection && document.selection.empty) dev.deselect = function() { document.selection.empty(); }
			else if(window.getSelection && window.getSelection().removeAllRanges) dev.deselect = function() { window.getSelection().removeAllRanges(); }
			else if(window.getSelection && window.getSelection().collapse) dev.deselect = function() { window.getSelection().collapse(); }
			else dev.deselect = function() {} //Leave as empty (khtml as of 4.2.2 does not support clearing selection)

			var quit = function(feature) //When fatal errors have occured, quit the system
			{
				var log = $system.log.init(_class + '.init.quit');
				log.dev($global.log.critical, 'dev/feature', 'dev/feature/solution', [feature]);

				$system.language.init(); //Force initialize the language function

				var language = $system.language.strings($id);
				alert(language['bad/' + feature]); //Try to be primitive to make sure this warning works
			}

			if(!window.navigator.cookieEnabled) return quit('cookie'); //Check for cookie feature availability

			if(window.XMLHttpRequest) dev.request = function() { return new XMLHttpRequest(); } //Try the cross engine method first
			else if(window.ActiveXObject) //For IE before version 7, use its own ActiveX implementation
			{
				try { dev.request = function() { return new ActiveXObject('Msxml2.XMLHTTP'); } } //Try newer version first

				catch(error) //On failure
				{
					try { dev.request = function() { return new ActiveXObject('Microsoft.XMLHTTP'); } } //Try an older version

					catch(error) { } //Give up otherwise (Errors are handled commonly below)
				}
			}

			//If it cannot initialize remote request feature, quit the whole thing since nothing will work
			if(typeof dev.request != 'function') return quit('request');

			//Note : window.navigator.language/browserLanguage only represents the language of the interface
			//and not the one configured by the user, which should be lower priority than the ones set by the user

			//The values set by the user in the browser sent to the server as HTTP_ACCEPT_LANGUAGE
			//cannot be retrieved directly on the client side, thus getting it back from the server as a cookie value

			//Fetch the client's preferred list of languages
			dev.language = decodeURIComponent(dev.cookie('language')).split(',');

			var displayed = window.navigator.language || window.navigator.browserLanguage; //Browser interface language
			if(!$system.is.text(displayed)) return; //If not valid, quit

			if(!$system.array.find(dev.language, displayed)) dev.language.push(displayed); //If not in the list, add to the list
			$system.browser.cookie('language', '', true); //Remove the cookie
		}

		this.cookie = function(key, value, remove) //Get, set or remove a cookie
		{
			if(!$system.is.text(key)) return false;

			if(remove) //Remove cookie
			{
				if(!$system.is.text(value, true)) return false;
				return document.cookie = key + '=' + value + '; expires=Thu, 01-Jan-1970 00:00:00 GMT';
			}

			if($system.is.text(value, true)) return document.cookie = key + '=' + value; //Set cookie
			return document.cookie.match(RegExp('(^|; ?)' + key + '=(.*?)(;|$)')) ? RegExp.$2 : ''; //Get cookie
		}

		this.report = function(error) //Receive the caught error from 'try/catch' statement and return the message
		{
			return error && error.description ? error.description : error; //IE uses 'description' property for error messages
		}

		this.scroll = function() //Gets the current amount of scrolling done within a browser window
		{
			//Get the appropriate values for scrolling
			var left = document.body.scrollLeft || document.documentElement.scrollLeft;
			var top = document.body.scrollTop || document.documentElement.scrollTop;

			return {x : left, y : top}; //Return the values
		}

		this.size = function() //Returns the size of the browser window
		{
			try
			{
				var width = (window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth);
				var height = (window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight);

				return {x : width, y : height};
			}

			catch(error) { return log.dev($global.log.error, 'dev/size', 'dev/size/solution', null, null, {}); }
		}
	}

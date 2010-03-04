
	$self.text = new function() //Text related class
	{
		var _class = $id + '.text';

		this.escape = function(text) //Escapes the HTML sensitive characters
		{
			text = text.replace(/&(?!(#?\w+;))/g, '&amp;'); //Do not over escape the entities
			return $system.text.replace(text, $system.array.list('" \' < >'), $system.array.list('&quot; &#039; &lt; &gt;'));
		}

		this.format = function(template, target, escape) //Returns a string from a preformatted string by associating variables to it
		{
			if(typeof template == 'number') template = String(template);

			if(!$system.is.text(template)) return '';
			if(!$system.is.object(target)) return template;

			if($system.is.array(target)) //If an array was passed
			{
				var counter = 0;

				var replace = function()
				{
					var value = String(target[counter++]);
					return escape ? $system.text.escape(value) : value;
				}

				return template.replace(/%%/g, replace); //Replace the symbol with each array value
			}
			else //If a hash was passed
			{
				var replace = function(phrase, match)
				{
					var value = String(target[match]);
					return escape ? $system.text.escape(value) : value;
				}

				return template.replace(/%(\w+?)%/g, replace); //Replace each symbols with the corresponding hash value
			}
		}

		this.link = function(text, local, tip) //Turn URL into HTML links
		{
			if(!$system.is.text(text)) return '';
			local = local ? '' : ' target="_blank"';

			//TODO - Also link texts starting with 'www.' or ending with '.com/net/org'
			//FIXME - If a mail address is within a link, and 'this.mail' is used together, linking breaks
			return text.replace(/\b((mailto|https?|ftp|irc):\/\/[\w\.,\-@%&\+=~\?\/:#;\*]+)/g, '<a href="$1"' + local + '>$1</a>');
		}

		this.mail = function(text, run, tip) //Turn mail addresses into function links
		{
			if(!$system.is.text(text)) return '';

			if(!$system.is.text(run)) run = $global.root + ".mail_1_0_0.gui.create('$1')"; //Give a default mail creation link
			run = $system.text.template(run).replace(/%match%/g, '$1');

			var component = '[\\w\.\-]+';
			return text.replace(RegExp('(' + component + '@' + component + '\\.' + component + ')', 'g'), '<a onclick="' + run + '">$1</a>');
		}

		this.regexp = function(text) //Escapes the string to be included inside a regular expression string
		{
			if(!$system.is.text(text)) return '';
			var sign = $system.array.list('/ . \\ ^ $ + - * ( ) [ ] ? { } |'); //List of meta characters to escape

			var source = ''; //String to be used for replacement source
			for(var i = 0; i < sign.length; i++) source += '\\' + sign[i]; //Escape before putting into a regular expression

			return text.replace(RegExp('([' + source + '])', 'g'), '\\$1'); //Replace the signs and add a backslash
		}

		this.replace = function(text, target, replace) //Replace multiple elements in a text
		{
			if(!$system.is.text(text)) return '';
			if(!$system.is.array(target) || !$system.is.array(replace)) return text;

			for(var i = 0; i < target.length; i++)
			{
				//Default to replacing all occurences
				if(typeof target[i] == 'string' || $system.is.digit(target[i], true, true)) target[i] = RegExp($system.text.regexp(target[i]), 'g');
				if(!(target[i] instanceof RegExp)) continue;

				if(typeof replace[i] == 'string' || $system.is.digit(replace[i], true, true) || typeof replace[i] == 'function')
					text = text.replace(target[i], replace[i]); //Replace the text for each target
			}

			return text; //Return the replaced text
		}

		this.template = function(text, id, prepare) //Replace all template variables
		{
			if(!$system.is.text(text)) return '';
			var action = $system.browser.os == 'iphone' ? 'ontouchstart' : 'onmousedown';

			var cancel = '; %top%.%system%.event.cancel(this, event)'; //To cancel event bubbling to avoid window dragging
			var lock = ' ' + action + '="' + cancel + '"'; //Same as cancel, but as a whole attribute
			var scroll = lock + ' onscroll="%top%.%system%.gui.clear(this, event)"'; //For scrolling events

			//Set the replace strings and values
			var target = [/%cancel%/g, /%lock%/g, /%scroll%/g, /%system%/g, /%top%/g, /%language%/g, /%brand%/g, /%brand_site%/g, /%brand_info%/g, /%developer%/g, /%developer_site%/g];
			var replace = [cancel, lock, scroll, $id, $global.root, $global.user.language.replace(/-.+$/, ''), $global.brand.name, $global.brand.site, $global.brand.info, $global.developer.name, $global.developer.site];

			if($system.is.id(id)) //If 'id' is set
			{
				if(!prepare) //If not for system template preparation, replace app specific variables
				{
					if(!$global.top[id] || !$global.top[id].info || !$global.top[id].info.title) var title = id;
					else var title = $global.top[id].info.title; //Look for the application's title

					target.push(/%title%/g, /%id%/g);
					replace.push(title, id);
				}

				//Replace any given tip links
				target.push(/%tip:(.+?)%/g);
				replace.push($system.app.method($system.tip.link, [id]));

				var image = function(phrase, match) { return $system.image.source(id, match); } //Replace the images with proper request address

				target.push(/%image:(.+?)%/g);
				replace.push(image);
			}

			return $system.text.replace(text, target, replace); //Replace each variables
		}

		//If the given value is not a string, return an empty string, otherwise as is
		this.value = function(value) { return typeof value == 'string' ? value : ''; }
	}

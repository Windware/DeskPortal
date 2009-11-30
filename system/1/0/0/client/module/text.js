
	$self.text = new function() //Text related class
	{
		var _class = $id + '.text';

		this.escape = function(text) //Escapes the HTML sensitive characters
		{
			return $system.text.replace(text, $system.array.list('& " \' < >'), $system.array.list('&amp; &quot; &#039; &lt; &gt;'));
		}

		this.format = function(template, target, escape) //Returns a string from a preformatted string by associating variables to it
		{
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

			var cancel = '; %top%.%system%.event.cancel(this, event)'; //To cancel event bubbling to avoid window dragging
			var lock = ' onmousedown="' + cancel + '"'; //Same as cancel, but as a whole attribute
			var scroll = lock + ' onscroll="%top%.%system%.gui.clear(this, event)"'; //For scrolling events

			//Set the replace strings and values
			var target = [/%cancel%/g, /%lock%/g, /%scroll%/g, /%system%/g, /%top%/g];
			var replace = [cancel, lock, scroll, $id, $global.root];

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
				text = text.replace(/%tip:(.+?)%/g, $system.app.method($system.tip.link, [id]));

				var image = function(phrase, match) { return $system.image.source(id, match); }
				text = text.replace(/%image:(.+?)%/g, image); //Replace the images with proper request address
			}

			//Replace the brand and developer information
			target.push(/%brand%/g, /%brand_site%/g, /%brand_info%/g, /%developer%/g, /%developer_site%/g);
			replace.push($global.brand.name, $global.brand.site, $global.brand.info, $global.developer.name, $global.developer.site);

			return $system.text.replace(text, target, replace); //Replace each variables
		}

		//If the given value is not a string, return an empty string, otherwise as is
		this.value = function(value) { return typeof value == 'string' ? value : ''; }
	}

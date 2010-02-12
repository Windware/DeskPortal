
	$self.date = new function() //Date manipulation class
	{
		var _class = $id + '.date';

		var _difference = 0; //The adjustment of time between client and the server for more accurate time calculation

		var _timestamp = function(date) { return date instanceof Date ? Math.floor(date.getTime() / 1000) : null; } //Get the timestamp

		var _date = function(date) //Object to initialize for date manipulation
		{
			var _self = this; //Keep the object reference

			var _pad = function(number, pad) { return pad && number < 10 ? '0' + number : number; } //Prepend 0 for numbers less than 10

			this.format = function(template) //Return the user configured date format string
			{
				if(_self.object === null || !$system.is.text(template)) return '';

				var replace = function(phrase, match) //Replace the template variables into the date object's value
				{
					var locale = $system.language.strings($id, 'date.xml');

					switch(match)
					{
						case 'Y' : return locale.year.replace('%%', _self.year()); break;

						case 'm' : return locale.month.replace('%%', _self.month()); break;

						case 'M' : return locale.month.replace('%%', _self.month('full')); break;

						case 'd' : return locale.day.replace('%%', _self.date()); break;

						case 'w' : return _self.day('less'); break;

						case 'W' : return _self.day('full'); break;

						case 't' : return _self.hour() < 12 ? local.am : locale.pm; break;

						case 'h' : return locale.hour.replace('%%', _self.hour()); break;

						case 'H' : return locale.hour.replace('%%', _self.hour(true)); break;

						case 'i' : return locale.hour.replace('%%', _self.hour(false, true)); break;

						case 'I' : return locale.hour.replace('%%', _self.hour(true, true)); break;

						case 'n' : return locale.minute.replace('%%', _self.minute()); break;

						case 'N' : return locale.minute.replace('%%', _self.minute(true)); break;

						case 's' : return _self.second(); break;

						case 'S' : return _self.second(true); break;
					}
				}

				return template.replace(/%(.+?)%/g, replace);
			}

			this.object = date; //Keep the date object accessible for manual processing

			this.timestamp = function() { return _timestamp(date); } //Unix timestamp (Seconds since 1970/1/1)

			this.second = function(pad) { return _pad(this.object.getSeconds(), pad); } //Second (0-59)

			this.minute = function(pad) { return _pad(this.object.getMinutes(), pad); } //Minute (0-59)

			this.hour = function(pad, less) //Hour (0-23 or 0-11)
			{
				var hour = this.object.getHours();
				if(less && hour >= 12) hour -= 12;

				return _pad(hour, pad);
			}

			this.date = function(pad) { return _pad(this.object.getDate(), pad); } //Day (1-31)

			this.day = function(name) //Day of the week (0-6 or in names)
			{
				return name == 'less' || name == 'full' ? $system.date.week[name][this.object.getDay()] : this.object.getDay();
			}

			this.month = function(name, pad) //Month (1-12 or in names)
			{
				return name == 'less' || name == 'full' ? $system.date.month[name][this.object.getMonth() + 1] : _pad(this.object.getMonth() + 1, pad);
			}

			this.year = function() { return this.object.getFullYear(); } //Year

			this.valid = !!date; //Set its validity
		}

		this.week = {less : [], full : []}; //Language specific names of weeks (Using 'short' for the keys causes a syntax error)

		this.month = {less : [], full : []}; //Language specific names of months

		this.init = function() //Prepare the names : TODO - Warn the user if the difference is bigger than 3 minutes
		{
			var section = ['week', 'month']; //For both of week and month
			_difference = Number($system.browser.cookie('time')) - _timestamp(new Date()); //Store the accuracy difference

			for(var i = 0; i < section.length; i++) //For both month and week
			{
				var xml = $system.language.file($id, section[i] + '.xml'); //Pick the language specific file
				var list = $system.dom.tags(xml, section[i]); //Get the name list node

				for(var j = 0; j < list.length; j++)
				{
					var number = $system.dom.attribute(list[j], 'number'); //Find the index number

					//Define the names
					this[section[i]].less[number] = $system.dom.attribute(list[j], 'less');
					this[section[i]].full[number] = $system.dom.attribute(list[j], 'full');
				}
			}

			$system.browser.cookie('time', '', true); //Let go of the cookie
		}

		this.create = function(source) //Create the date object from a time source
		{
			var log = $system.log.init(_class + '.create');

			if($system.is.digit(source)) //If a timestamp is given
			{
				var date = new Date(source * 1000); //Initialize a date object
				if(date.getTime() / 1000 != source) date = null; //Check for validness
			}
			else if($system.is.array(source)) //If an array of time is given
			{
				if($system.is.digit(source[0])) //At least year is required
				{
					//Give default values
					if(source[1] === undefined) source[1] = 1; //Month
					if(source[2] === undefined) source[2] = 1; //Day
					if(source[3] === undefined) source[3] = 0; //Hour
					if(source[4] === undefined) source[4] = 0; //Minute
					if(source[5] === undefined) source[5] = 0; //Second

					var date = new Date(source[0], source[1] - 1, source[2], source[3], source[4], source[5]);

					//Check for the date's validness by matching the date values to the value specified
					if(source[0] != date.getFullYear() || source[1] != date.getMonth() + 1 || source[2] != date.getDate()) date = null;
					else if(source[3] != date.getHours() || source[4] != date.getMinutes() || source[5] != date.getSeconds()) date = null;
					else date = new Date(Date.UTC(source[0], source[1] - 1, source[2], source[3], source[4], source[5])); //Create local time from GMT
				}
			}
			else if(String(source).match(/^(\d+-\d+-\d+)( \d+:\d+:\d+)?$/)) //If a DATETIME string is given
			{
				var date = RegExp.$1.split('-');
				var time = RegExp.$2 ? RegExp.$2.replace(/^ /, '').split(':') : [];

				return $system.date.create([date[0], date[1], date[2], time[0], time[1], time[2]]);
			}
			else if(source instanceof Date) date = source; //If a date object is passed directly
			//Use current time and adjust the time by the value given from the server
			else if(source === undefined) return $system.date.create(_timestamp(new Date()) + _difference);

			return date && !isNaN(date.getDate()) ? new _date(date) : new _date(null); //Create the date function object if it succeeds
		}

		this.select = function(start, end, descend, selected, pad, step) //Creates 'option' nodes to be appened on 'select' form
		{
			var log = $system.log.init(_class + '.select');
			if(!$system.is.digit(start) || !$system.is.digit(end)) return log.param();

			if(step === undefined) step = 1; //Increment by 1 if not specified
			if(!$system.is.digit(step) || step == 0) return log.param();

			if(!$system.is.digit(selected)) selected = null; //Keep it safely ignored

			if(start > end) //Swap if ordered wrong
			{
				var temp = start;

				start = end;
				end = temp;
			}

			var choices = ''; //Options HTML

			var append = function(i)
			{
				var display = (pad && i < 10) ? '0' + i : i;
				var pick = (i == selected) ? ' selected="selected"' : '';

				choices += $system.text.format('<option value="%%"%%>%%</option>', [i, pick, display]);
			}

			if(!descend) for(var i = start; i <= end; i += step) append(i);
			else for(var i = end; i >= start; i -= step) append(i);

			return choices; //Return the node HTML
		}
	}

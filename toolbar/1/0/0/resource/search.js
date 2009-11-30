
	$self.search = new function()
	{
		var _class = $id + '.search';

		this.dictionary = function(value, mode) //Dictionary search
		{
			var log = $system.log.init(_class + '.dictionary');
			if(!value.length) return log.param();

			switch(mode)
			{
				case 'en' : var address = 'http://www.google.com/dictionary?q=%%&hl=%%&langpair=en%7Cen'; break;

				case 'ja' : var address = 'http://dic.yahoo.co.jp/dsearch?p=%%&enc=UTF-8&stype=0&dtype=0'; break;

				default : return log.praram(); break;
			}

			open($system.text.format(address, [value, $global.user.language]));
		}

		this.web = function(value, mode) //Web search
		{
			var log = $system.log.init(_class + '.web');
			if(!value.length) return log.param();

			switch(mode) //TODO - Add language parameter
			{
				case 'page' : var address = 'http://www.google.com/search?hl=%%&q=%%'; break;

				case 'image' : var address = 'http://image.google.com/images?hl=%%&q=%%'; break;

				default : return log.praram(); break;
			}

			open($system.text.format(address, [$global.user.language, value]));
		}

		this.wikipedia = function(value, mode) //Wikipedia search
		{
			var log = $system.log.init(_class + '.wikipedia');
			if(!value.length) return log.param();

			switch(mode)
			{
				case 'en' : var address = 'http://en.wikipedia.org/wiki/%%'; break;

				case 'ja' : var address = 'http://ja.wikipedia.org/wiki/%%'; break;

				default : return log.praram(); break;
			}

			//Adhere to wikipedia page naming first letter capitalization to avoid being redirected
			value = value.charAt(0).toUpperCase() + value.substr(1).toLowerCase();
			open(address.replace('%%', value));
		}
	}

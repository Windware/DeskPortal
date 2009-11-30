
	$self.convert = new function()
	{
		var _class = $id + '.convert';

		var _digit = 5; //Default amount of decimal digits to keep from the results

		var _round = function(value, decimal) //Round a number
		{
			if(!$system.is.digit(value, true, true)) return false;
			if(!$system.is.digit(decimal)) decimal = _digit; //Set default accuracy digit

			var accuracy = Math.pow(10, decimal);
			var rounded = Math.round(value * accuracy) / accuracy; //Round with the given accurary

			//Add floating point 0 to rounded decimal values to indicate the accuracy limit of the result
			return (parseInt(value) != value && parseInt(rounded) == rounded) ? String(rounded) + '.00' : rounded;
		}

		this.currency = function(value, mode, target)
		{
			var log = $system.log.init(_class + '.currency');
			if(!$system.is.digit(value, true, true) || !mode || !target) return log.param();

			var address = 'http://www.google.com/finance/converter?a=%%&from=%%&to=%%';
			open($system.text.format(address, [value, mode.toUpperCase(), target.toUpperCase()]));

			return true;
		}

		this.language = function(value, mode, target)
		{
			var log = $system.log.init(_class + '.language');
			if(!$system.is.text(value) || !$system.is.language(mode) || !$system.is.language(target)) return log.param();

			var address = 'http://translate.google.com/translate_t?hl=%%#%%|%%|%%';
			open($system.text.format(address, [$global.user.language, mode, target, encodeURIComponent(value)]));

			return true;
		}

		this.length = function(value, mode, target)
		{
			var log = $system.log.init(_class + '.length');
			if(!$system.is.digit(value, true, true)) return log.param();

			var base = function(unit) //Base is 'meter'
			{
				switch(target)
				{
					case 'm' : return unit;

					case 'mm' : return unit * 1000;

					case 'cm' : return unit * 100;

					case 'km' : return unit / 1000;

					case 'ly' : return unit / 9460730472580800;

					case 'ft' : return unit / 0.3048;

					case 'in' : return unit / 0.0254;

					default : return log.param();
				}
			}

			switch(mode)
			{
				case 'm' : return _round(base(value));

				case 'mm' : return _round(base(value / 1000));

				case 'cm' : return _round(base(value / 100));

				case 'km' : return _round(base(value * 1000));

				case 'ly' : return _round(base(value * 9460730472580800));

				case 'ft' : return _round(base(value * 0.3048));

				case 'in' : return _round(base(value * 0.0254));

				default : return log.param();
			}
		}

		this.mass = function(value, mode, target)
		{
			var log = $system.log.init(_class + '.mass');
			if(!$system.is.digit(value, true, true)) return log.param();

			var base = function(unit) //Base is 'gram'
			{
				switch(target)
				{
					case 'g' : return unit;

					case 'mg' : return unit * 1000;

					case 'kg' : return unit / 1000;

					case 'lb' : return unit / 0.002204623;

					case 'oz' : return unit / 0.0352739619;

					case 't' : return unit / 1000000;

					case 'ml' : return unit;

					case 'dl' : return unit / 100;

					case 'l' : return unit / 1000;

					case 'kl' : return unit / 1000000;

					default : return log.param();
				}
			}

			switch(mode)
			{
				case 'g' : return _round(base(value));

				case 'mg' : return _round(base(value / 1000));

				case 'kg' : return _round(base(value * 1000));

				case 'lb' : return _round(base(value * 0.002204623));

				case 'oz' : return _round(base(value * 0.0352739619));

				case 't' : return _round(base(value * 1000000));

				case 'ml' : return _round(base(value));

				case 'dl' : return _round(base(value * 100));

				case 'l' : return _round(base(value * 1000));

				case 'kl' : return _round(base(value * 1000000));

				default : return log.param();
			}
		}

		this.speed = function(value, mode, target)
		{
			var log = $system.log.init(_class + '.speed');
			if(!$system.is.digit(value, true, true)) return log.param();

			var base = function(unit) //Base is 'kph'
			{
				switch(target)
				{
					case 'kph' : return unit;

					case 'mph' : return unit / 1.609344;

					case 'Ma' : return unit / 1225;

					case 'kt' : return unit / 1.85200;

					default : return log.param();
				}
			}

			switch(mode)
			{
				case 'kph' : return _round(base(value));

				case 'mph' : return _round(base(value * 1.609344));

				case 'Ma' : return _round(base(value * 1225));

				case 'kt' : return _round(base(value * 1.85200));

				default : return log.param();
			}
		}

		this.temperature = function(value, mode, target)
		{
			var log = $system.log.init(_class + '.temperature');
			if(!$system.is.digit(value, true, true)) return log.param();

			var base = function(unit) //Base is 'celsius'
			{
				switch(target)
				{
					case 'C' : return unit;

					case 'F' : return unit * 9 / 5 + 32;

					default : return log.param();
				}
			}

			switch(mode)
			{
				case 'C' : return _round(base(value));

				case 'F' : return _round(base((value - 32) * 5 / 9));

				default : return log.param();
			}
		}

		this.time = function(value, mode, target)
		{
			var log = $system.log.init(_class + '.time');
			if(!$system.is.digit(value, true, true)) return log.param();

			var base = function(unit) //Base is 'day'
			{
				switch(target)
				{
					case 'year' : return unit / 365;

					case 'month' : return unit / 30;

					case 'week' : return unit / 7;

					case 'day' : return unit;

					case 'hour' : return unit * 24;

					case 'minute' : return unit * 24 * 60;

					case 'second' : return unit * 24 * 3600;

					default : return log.param();
				}
			}

			switch(mode)
			{
				case 'year':
					switch(target)
					{
						case 'year' : return value;

						case 'month' : return value * 12;

						default : return _round(base(value * 365));
					}
				break;

				case 'month':
					switch(target)
					{
						case 'year' : return value / 12;

						case 'month' : return value;

						default : return _round(base(value * 30));
					}
				break;

				case 'week' : return _round(base(value * 7));

				case 'day' : return _round(base(value));

				case 'hour' : return _round(base(value / 24));

				case 'minute' : return _round(base(value / 24 / 60));

				case 'second' : return _round(base(value / 24 / 3600));

				default : return log.param();
			}
		}
	}

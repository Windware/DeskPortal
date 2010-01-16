
	new function()
	{
		var conf = $global.user.conf[$id]; //Set default values

		if(!$system.is.digit(conf.level)) conf.level = 1;
		if(!$system.is.digit(conf.period)) conf.period = 10;

		if(!$system.is.digit(conf.animated)) conf.animated = 1;
		if(!$system.is.digit(conf.dev)) conf.dev = 0;
	}

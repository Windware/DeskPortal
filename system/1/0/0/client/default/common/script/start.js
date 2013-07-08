
	//This file gets loaded first after 'start.js' in static system version only if this is the core system
	//and not at all if loaded by another system version

	//'%'varaibles will be expanded on the server side
	%root%.system_%version% = {}; //Create the system object first, so that this object exists during the initialization

	new function() //Initialize the core scope and the system (Does what app.library function does, since it's not ready yet)
	{
		var $global = %root%.system_static; //Keep the globally used variables easily reachable

		var $self = %root%.system_%version%; //Keep the system itself accessible from own scripts
		var $system = $self; //Alias the system as itself

		$self.info = new function() //Attribute declarations
		{
			this.name = 'system'; //Name of the application
			this.version = '%version%'; //Version of the application

			this.require = []; //Engine requirement declaration

			this.id = this.name + '_' + this.version; //ID of the application (Name and version concatenated)
			this.root = this.id.replace(/_/g, '/') + '/'; //Root folder of the application

			this.top = $global.root + '.' + this.id; //Reference from the top level node to be used for inside HTML
			this.devroot = null; //Root folder of the device and theme specific components (Will be defined later after device is identified)

			this.preload = []; //List of files to preload for this application
			this.template = {}; //HTML template caches
		}

		var $id = $self.info.id; //Own ID

		$global.depend[$id] = []; //Lists which other applications the application depends on
		$global.system = $self; //Declare this system version as the core

		$global.user.conf[$id] = {}; //Set the user preference list
		%root%.start = function() { $self.run(); } //Link the top level 'main.start' as this system's 'start' function

		//Load other system scripts
%run%
	}

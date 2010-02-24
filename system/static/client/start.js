
	var %root% = //Create the top most object of the entire system in the DOM tree
	{
		system_static : new function()
		{
			var $self = this;

			this.app = {}; //Application information

			this.brand = {name : '%brand%', site : '%brand_site%', info : '%brand_info%'}; //Name of the system

			this.depend = []; //List of reverse dependency for each application

			this.developer = {name : '%developer%', site : '%developer_site%'}; //The developer information

			this.demo = {mode : %demo%, user : '%demo_user%', pass : '%demo_pass%'}; //Whether this is under demo mode or not

			this.extensions = {%extension%}; //List of available server side languages and their extension

			this.loader = {library : {}, load : {}, unload : {}}; //Links to loading functions for systems

			this.log = //Log related values
			{
				reports : {dev : [], system : [], user : [], unknown : []}, //List of logs collected from each applications

				names : ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'], //Name of log levels

				add : function(section, level, message, solution, origin, reporter) //Add the log to the global variable
				{
					if(!$self.user.pref.debug) return;

					var report = {section : section, level : level, message : message, solution : solution, origin : origin, reporter : reporter};
					var stack = $self.log.reports; //Keep the report in the global variable

					if(!stack[section]) section = 'unknown'; //Stack onto 'unknown' log array
					if(!stack[section][level]) level = 0; //Stack onto level '0' array if the level is invalid

					stack[section][level].push(report);
					if(level > $self.log.alert) return;

					var display = '';
					for(var key in report) display += key + ' : ' + report[key] + '\n';

					alert(display); //Make alerts for levels specified below
				}
			};

			for(var i = 0; i <= 7; i++) //For all the log levels
			{
				//Create initial log containers
				this.log.reports.dev[i] = [];
				this.log.reports.system[i] = [];
				this.log.reports.user[i] = [];
				this.log.reports.unknown[i] = [];

				this.log[this.log.names[i]] = i; //Set the log level values
			}

			//this.online = false; //Status if the system is online or offline (NOTE : Not used now)

			this.root = '%root%'; //The root node name of the system in the DOM tree

			this.start = null; //The main function to be executed first

			this.system = null; //The core system version to be used first

			this.user = {conf : {}, id : null, language : 'en', loaded : [], ticket : null, used : {}, window : {}}; //The logged in user's information

			this.user.pref = this.user.conf['system_static'] = //Initialize client side configuration with default values
			{
				session : false, //Whether the user cookie tickets should expire on the browser session or not
				logout : 30, //Time in minutes to logout the user automatically, if the interface is never clicked for this duration

				debug : false, //Turn on debug mode or not (For log tracking and showing developer messages etc)
				log : this.log.notice, //Level of logs to report
				alert : this.log.critical, //Level of logs to alert on screen

				wallpaper : '', //User's wallpaper file
				language : '', //Preferred language to be used (Different from this.user.language, which is the displayed language)
				format : {}, //Date display format

				round : true, //If the window should have rounded corners or not
				fade : false, //To fade windows and elements or not (Can degrade performance when 'true')
				translucent : false, //Whether to make windows translucent or not while moving and resizing
				animate : false, //If shrink/expand operations should be done with animation (Not used yet as of system_1_0_0)

				delay : 1, //Delay in seconds till a tip is shown
				move : 1, //Type of window movement

				resize : true, //Resize the wallpaper or not
				stretch : true, //If resizing, stretch the wallpaper or not
				center : false, //Put the wallpaper in center or not. Unused if stretch is true.

				background : '333333' //Background color where it is not covered by a wallpaper
			};
		}
	};

	%root%.system_static.top = %root%; //Give a relative reference to the top most object for other applications


	//Code to be loaded after other modules are read
	//Note that the order of execution is important as some modules rely on other modules to be initiated

	new function()
	{
		$system.app.reload(); //In case this system is loaded from another version, make sure all environments are configured

		//Link the system loading functions
		$global.loader.library[$id] = $system.app.library;
		$global.loader.load[$id] = $system.app.load;
		$global.loader.unload[$id] = $system.app.unload;

		$system.browser.init(); //Detect and initialize browser specific functions

		if($self == $global.system) //If this is the first loaded core system
		{
			$global.user.language = $system.language.pref(); //Set the preferred language to use

			//Set browser specific component folder
			$system.info.devroot = $system.text.format('%%component/%%/%%/', [$system.info.root, $global.user.conf[$id].theme, $system.browser.type]);

			if($system.is.md5($global.user.ticket)) //If the login ticket is available
			{
				$system.info.depend = []; //The list of application the system depends on
				var list = $global.user.loaded; //Find out which applications the user has

				//Add them as initial dependency to the system if the application is set to be displayed on the screen loaded
				for(var i = 0 ; i < list.length; i++) $system.info.depend.push(list[i]);
			}
			else
			{
				//System does not depend on other applications,
				//but adding these dependencies will automatically load their init.js in a preload package to decrease amount of requests
				//But do NOT add those applications as reverse dependencies to the system or the system will try to unload itself upon them getting unloaded
				$system.info.depend = $system.array.list('about_1_0_0 announce_1_0_0 login_1_0_0');
			}

			//Preload files, except the ones already loaded through server side (By setting second parameter to 'true')
			$system.app.prepare($system.info.id, true);
		}

		$system.window.init(); //Initialize window templates
		$system.date.init(); //Initialize the date module (After required XML are loaded by '$system.app.prepare')

		$system.language.init(); //Initialize the supported language list
		$system.motion.init(); //Initialize mouse drag function

		//Load common stylesheet and engine specific stylesheet
		$system.style.add($system.info.id, 'common.css');
		$system.style.add($system.info.id, $system.browser.engine + '.css');
	}
